<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiSearchServiceInterface;
use App\Models\AdType;
use App\Models\City; // used in enrichWithIds
use App\Models\Quarter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Parses natural language real-estate queries into structured search parameters using an LLM.
 *
 * Tries each configured provider in order (AI_SEARCH_PROVIDERS env, default: groq,openai,gemini).
 * Each provider has its own circuit breaker — when one exhausts quota or fails 3 times,
 * the next provider is tried automatically, with no user-visible degradation.
 * Falls back to regex-based parsing only when ALL providers fail or are unavailable.
 * Results are cached for 24 hours per query.
 */
class AiSearchService implements AiSearchServiceInterface
{
    private const int CACHE_TTL_SECONDS = 86400; // 24 hours

    private const string CACHE_PREFIX = 'ai_search:';

    private const string CONTEXT_CACHE_KEY = 'ai_search:context';

    private const int CONTEXT_CACHE_TTL = 21600; // 6 hours

    private const int CIRCUIT_FAILURE_THRESHOLD = 3;

    private const int CIRCUIT_OPEN_TTL = 300; // 5 minutes

    /**
     * OpenAI-compatible providers: same request/response shape, only URL + model differ.
     * Gemini uses a separate code path.
     *
     * @var array<string, array{url: string, config_key: string, default_model: string}>
     */
    private const array OPENAI_COMPATIBLE = [
        'groq' => ['url' => 'https://api.groq.com/openai/v1/chat/completions',    'config_key' => 'services.groq',     'default_model' => 'llama-3.3-70b-versatile'],
        'openai' => ['url' => 'https://api.openai.com/v1/chat/completions',          'config_key' => 'services.openai',   'default_model' => 'gpt-4o-mini'],
        'together' => ['url' => 'https://api.together.xyz/v1/chat/completions',        'config_key' => 'services.together', 'default_model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo'],
        'mistral' => ['url' => 'https://api.mistral.ai/v1/chat/completions',          'config_key' => 'services.mistral',  'default_model' => 'mistral-small-latest'],
    ];

    /**
     * Parse a property image into structured search parameters.
     *
     * Tries GPT-4o-vision first, then Gemini Vision. Image results are NOT
     * cached (each upload is unique). Returns the same structure as parse().
     *
     * @return array<string, mixed>
     */
    public function parseFromImage(string $base64Image, string $mimeType = 'image/jpeg'): array
    {
        $result = $this->parseImageWithOpenAiVision($base64Image, $mimeType)
            ?? $this->parseImageWithGeminiVision($base64Image, $mimeType);

        if ($result === null) {
            return $this->emptyResult('image_search');
        }

        return $this->enrichWithIds($result, 'image_search');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseImageWithOpenAiVision(string $base64Image, string $mimeType): ?array
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $model = 'gpt-4o';
        $systemPrompt = $this->systemPrompt($this->buildContext());
        $userText = 'Analyse cette photo de bien immobilier et extrais les critères de recherche. Réponds UNIQUEMENT avec un objet JSON valide, sans markdown ni texte autour.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $userText],
                            ['type' => 'image_url', 'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}",
                                'detail' => 'low',
                            ]],
                        ]],
                    ],
                    'max_tokens' => 400,
                    'temperature' => 0.1,
                ]);

            if ($response->failed()) {
                Log::warning('AiSearchService: [openai-vision] HTTP '.$response->status());

                return null;
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));
            $decoded = $content !== '' ? $this->extractJson($content) : null;

            return $decoded !== null ? $this->normalizeParsedResult($decoded) : null;
        } catch (\Throwable $e) {
            Log::error('AiSearchService: [openai-vision] '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseImageWithGeminiVision(string $base64Image, string $mimeType): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $systemPrompt = $this->systemPrompt($this->buildContext());
        $userText = 'Analyse cette photo de bien immobilier et extrais les critères de recherche. Réponds UNIQUEMENT avec un objet JSON valide, sans markdown ni texte autour.';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => "{$systemPrompt}\n\n{$userText}"],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]],
                    ],
                ]],
                'generationConfig' => ['maxOutputTokens' => 400, 'temperature' => 0.1],
            ]);

            if ($response->failed()) {
                Log::warning('AiSearchService: [gemini-vision] HTTP '.$response->status());

                return null;
            }

            $content = trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
            $decoded = $content !== '' ? $this->extractJson($content) : null;

            return $decoded !== null ? $this->normalizeParsedResult($decoded) : null;
        } catch (\Throwable $e) {
            Log::error('AiSearchService: [gemini-vision] '.$e->getMessage());

            return null;
        }
    }

    /**
     * Parse a natural language query into structured search parameters.
     *
     * @return array<string, mixed>
     */
    public function parse(string $query): array
    {
        $normalized = $this->preNormalizeQuery(mb_strtolower(trim($query)));
        if ($normalized === '') {
            return $this->emptyResult($query);
        }

        $cacheKey = self::CACHE_PREFIX.md5($normalized);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($normalized, $query) {
            $result = $this->tryAllProviders($normalized);
            if ($result !== null) {
                return $this->enrichWithIds($result, $query);
            }

            return (new NaturalSearchRegexParser)->parse($normalized);
        });
    }

    /**
     * Normalise written French numeric multipliers so both the LLM and the regex
     * parser receive unambiguous digit strings.
     * Examples: "50 milles" → "50000", "2 millions" → "2000000"
     */
    private function preNormalizeQuery(string $query): string
    {
        // mille / milles / millier / milliers  → ×1 000
        $query = (string) preg_replace_callback(
            '/(\d+)\s*mill(?:e|es|ier|iers)\b/u',
            static fn ($m) => (string) ((int) $m[1] * 1000),
            $query
        );
        // million / millions → ×1 000 000
        $query = (string) preg_replace_callback(
            '/(\d+)\s*millions?\b/u',
            static fn ($m) => (string) ((int) $m[1] * 1_000_000),
            $query
        );

        return $query;
    }

    /**
     * Try each configured provider in order. Returns parsed result from the first that succeeds.
     * Skips providers whose circuit is open or whose API key is not set.
     *
     * @return array<string, mixed>|null
     */
    private function tryAllProviders(string $query): ?array
    {
        $providers = array_filter(
            array_map(trim(...), explode(',', (string) config('services.ai_search.providers', 'groq,openai,gemini')))
        );

        foreach ($providers as $name) {
            if (Cache::has($this->circuitKey($name))) {
                Log::debug("AiSearchService: circuit open for [{$name}], skipping.");

                continue;
            }

            $result = isset(self::OPENAI_COMPATIBLE[$name])
                ? $this->parseWithOpenAiCompatible($name, $query)
                : ($name === 'gemini' ? $this->parseWithGemini($query) : null);

            if ($result !== null) {
                $this->resetCircuit($name);
                Log::debug("AiSearchService: parsed successfully via [{$name}].");

                return $result;
            }
        }

        Log::warning('AiSearchService: all providers failed or skipped, falling back to regex.');

        return null;
    }

    /**
     * Call any OpenAI-compatible provider (Groq, OpenAI, Together AI, Mistral…).
     *
     * @return array<string, mixed>|null
     */
    private function parseWithOpenAiCompatible(string $name, string $query): ?array
    {
        $cfg = self::OPENAI_COMPATIBLE[$name];
        $apiKey = config("{$cfg['config_key']}.api_key");
        if (empty($apiKey)) {
            return null;
        }

        $model = config("{$cfg['config_key']}.model", $cfg['default_model']);
        $systemPrompt = $this->systemPrompt($this->buildContext());
        $userPrompt = "Requête de l'utilisateur : \"{$query}\"\n\nRéponds UNIQUEMENT avec un objet JSON valide, sans markdown ni texte autour.";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(8)
                ->post($cfg['url'], [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => 400,
                    'temperature' => 0.1,
                ]);

            if ($response->failed()) {
                Log::warning("AiSearchService: [{$name}] HTTP {$response->status()}", ['body' => substr($response->body(), 0, 200)]);
                $this->recordFailure($name);

                return null;
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));
            $decoded = $content !== '' ? $this->extractJson($content) : null;
            if ($decoded === null) {
                Log::warning("AiSearchService: [{$name}] invalid JSON", ['content' => substr($content, 0, 200)]);
                $this->recordFailure($name);

                return null;
            }

            return $this->normalizeParsedResult($decoded);
        } catch (\Throwable $e) {
            Log::error("AiSearchService: [{$name}] exception: ".$e->getMessage());
            $this->recordFailure($name);

            return null;
        }
    }

    /**
     * Call Google Gemini (generateContent API — different format from OpenAI).
     *
     * @return array<string, mixed>|null
     */
    private function parseWithGemini(string $query): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $systemPrompt = $this->systemPrompt($this->buildContext());
        $userPrompt = "Requête de l'utilisateur : \"{$query}\"\n\nRéponds UNIQUEMENT avec un objet JSON valide, sans markdown ni texte autour.";
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(8)->post($url, [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => "{$systemPrompt}\n\n{$userPrompt}"]]],
                ],
                'generationConfig' => ['maxOutputTokens' => 400, 'temperature' => 0.1],
            ]);

            if ($response->failed()) {
                Log::warning('AiSearchService: [gemini] HTTP '.$response->status(), ['body' => substr($response->body(), 0, 200)]);
                $this->recordFailure('gemini');

                return null;
            }

            $content = trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
            $decoded = $content !== '' ? $this->extractJson($content) : null;
            if ($decoded === null) {
                Log::warning('AiSearchService: [gemini] invalid JSON', ['content' => substr($content, 0, 200)]);
                $this->recordFailure('gemini');

                return null;
            }

            return $this->normalizeParsedResult($decoded);
        } catch (\Throwable $e) {
            Log::error('AiSearchService: [gemini] exception: '.$e->getMessage());
            $this->recordFailure('gemini');

            return null;
        }
    }

    private function circuitKey(string $provider): string
    {
        return "ai_search:circuit:{$provider}";
    }

    private function failureKey(string $provider): string
    {
        return "ai_search:failures:{$provider}";
    }

    private function recordFailure(string $provider): void
    {
        $key = $this->failureKey($provider);
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, self::CONTEXT_CACHE_TTL);

        if ($failures >= self::CIRCUIT_FAILURE_THRESHOLD) {
            Cache::put($this->circuitKey($provider), true, self::CIRCUIT_OPEN_TTL);
            Log::warning("AiSearchService: circuit opened for [{$provider}] after {$failures} failures.");
        }
    }

    private function resetCircuit(string $provider): void
    {
        Cache::forget($this->failureKey($provider));
        Cache::forget($this->circuitKey($provider));
    }

    private function systemPrompt(string $context): string
    {
        return <<<PROMPT
Tu es le moteur d'extraction de critères immobiliers de KeyHome (marketplace immobilière, Cameroun / Afrique centrale, prix en FCFA).

Ta SEULE tâche : analyser la requête et retourner un objet JSON valide.
IMPORTANT : commence DIRECTEMENT par { — aucun texte, markdown, ou commentaire avant ou après le JSON.

## SCHÉMA DE SORTIE
Retourne exactement ces 11 clés. Utilise null si un critère n'est pas mentionné.
{
  "transaction_type": <"location"|"vente"|null>,
  "type_name":        <string|null>,
  "city_name":        <string|null>,
  "quarter_name":     <string|null>,
  "bedrooms":         <int|null>,
  "price_min":        <int|null>,
  "price_max":        <int|null>,
  "surface_min":      <int|null>,
  "has_parking":      <bool|null>,
  "furnished":        <bool|null>,
  "q":                <string|null>
}

## RÈGLES D'EXTRACTION

### TYPE DE TRANSACTION (transaction_type)
- "louer"/"location"/"à louer"/"en location"/"à la location"/"/mois"/"mensuel"/"par mois"/"loue" → "location"
- "acheter"/"achat"/"à vendre"/"en vente"/"vendre"/"vente"/"acquisition" → "vente"
- Si non mentionné explicitement → null. Ne JAMAIS déduire depuis le type de bien ou le prix.

### PRIX (toujours en FCFA)
- Conversions : "150k"→150000, "50 milles"/"50 mille"→50000, "1,5M"/"1.5 million"→1500000, "2M"→2000000.
- "mille"/"milles"/"millier" = ×1 000 (PAS ×1 000 000). Ex : "50 milles" = 50 000 FCFA.
- "pas cher"/"budget serré"/"économique" → price_max: 80000 si location, 8000000 si vente, 80000 si null.
- "haut de gamme"/"luxe"/"standing" → price_min: 300000 si location, 50000000 si vente, 300000 si null.

### TYPE DE BIEN
- Utilise UNIQUEMENT les noms exacts de la liste fournie dans le référentiel.
- Synonymes courants :
  "appart"/"appartement"/"flat" → cherche "Appartement" dans la liste
  "studio" → cherche "Studio" dans la liste (jamais "Appartement")
  "villa"/"duplex"/"bungalow"/"maison"/"domicile" → cherche "Maison" ou "Villa" dans la liste
  "boutique"/"commerce"/"bureau"/"local commercial"/"magasin" → cherche "Commerce" dans la liste
  "terrain"/"parcelle"/"lot" → cherche "Terrain" dans la liste

### CHAMBRES (bedrooms)
- Met null sauf si un nombre est EXPLICITEMENT mentionné dans la requête.
- Ne JAMAIS déduire bedrooms depuis le type : studio → bedrooms: null.
- Correspondances locales : "F1"/"T1"→1, "F2"/"T2"/"2 pièces"→2, "F3"/"T3"/"3 pièces"→3, etc.
- "chambre salon" / "salon chambre" → bedrooms: 1.
- "2 chambres" / "3 chambres" / "4 pièces" → bedrooms: valeur explicite.

### VILLE ET QUARTIER
- Utilise UNIQUEMENT les noms exacts de la liste du référentiel. Si absent → null.

### MOTS-CLÉS RÉSIDUELS (q)
- Remplis "q" avec les termes descriptifs non structurés (ex: "piscine", "vue mer", "neuf", "calme").
- Si tous les critères sont structurés, laisse q: null.
- Si la requête est entièrement vague (aucun critère), mets tout à null et q = requête complète.

## EXEMPLES

Requête : "je cherche un studio meublé à louer à Yaoundé moins de 80 000 fcfa"
Réponse : {"transaction_type":"location","type_name":"Studio","city_name":"Yaoundé","quarter_name":null,"bedrooms":null,"price_min":null,"price_max":80000,"surface_min":null,"has_parking":null,"furnished":true,"q":null}

Requête : "appartement F3 avec parking à Bonapriso Douala"
Réponse : {"transaction_type":null,"type_name":"Appartement","city_name":"Douala","quarter_name":"Bonapriso","bedrooms":3,"price_min":null,"price_max":null,"surface_min":null,"has_parking":true,"furnished":null,"q":null}

Requête : "villa à vendre luxueuse avec piscine à Bastos Yaoundé"
Réponse : {"transaction_type":"vente","type_name":"Maison","city_name":"Yaoundé","quarter_name":"Bastos","bedrooms":null,"price_min":50000000,"price_max":null,"surface_min":null,"has_parking":null,"furnished":null,"q":"piscine luxe"}

Requête : "terrain constructible 500m² à acheter Douala"
Réponse : {"transaction_type":"vente","type_name":"Terrain","city_name":"Douala","quarter_name":null,"bedrooms":null,"price_min":null,"price_max":null,"surface_min":500,"has_parking":null,"furnished":null,"q":null}

Requête : "chambre salon meublée à louer pas cher avec parking"
Réponse : {"transaction_type":"location","type_name":null,"city_name":null,"quarter_name":null,"bedrooms":1,"price_min":null,"price_max":80000,"surface_min":null,"has_parking":true,"furnished":true,"q":null}

Requête : "logement pour étudiant"
Réponse : {"transaction_type":null,"type_name":null,"city_name":null,"quarter_name":null,"bedrooms":null,"price_min":null,"price_max":null,"surface_min":null,"has_parking":null,"furnished":null,"q":"logement étudiant"}

## RÉFÉRENTIEL DISPONIBLE
{$context}
PROMPT;
    }

    private function buildContext(): string
    {
        return Cache::remember(self::CONTEXT_CACHE_KEY, self::CONTEXT_CACHE_TTL, function () {
            $cities = City::with('quarters')->get();
            $types = AdType::all();

            $cityList = $cities->map(fn (City $c) => $c->name.': '.$c->quarters->pluck('name')->join(', '))->join("\n");
            $typeList = $types->pluck('name')->join(', ');

            return "Villes et quartiers :\n{$cityList}\n\nTypes de biens : {$typeList}";
        });
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalizeParsedResult(array $parsed): array
    {
        $validTxTypes = ['location', 'vente'];

        return [
            'transaction_type' => isset($parsed['transaction_type']) && in_array($parsed['transaction_type'], $validTxTypes, true) ? (string) $parsed['transaction_type'] : null,
            'type_name' => isset($parsed['type_name']) && $parsed['type_name'] !== '' ? (string) $parsed['type_name'] : null,
            'city_name' => isset($parsed['city_name']) && $parsed['city_name'] !== '' ? (string) $parsed['city_name'] : null,
            'quarter_name' => isset($parsed['quarter_name']) && $parsed['quarter_name'] !== '' ? (string) $parsed['quarter_name'] : null,
            'bedrooms' => isset($parsed['bedrooms']) && is_numeric($parsed['bedrooms']) ? (int) $parsed['bedrooms'] : null,
            'price_min' => isset($parsed['price_min']) && is_numeric($parsed['price_min']) ? (int) $parsed['price_min'] : null,
            'price_max' => isset($parsed['price_max']) && is_numeric($parsed['price_max']) ? (int) $parsed['price_max'] : null,
            'surface_min' => isset($parsed['surface_min']) && is_numeric($parsed['surface_min']) ? (int) $parsed['surface_min'] : null,
            'has_parking' => isset($parsed['has_parking']) ? (bool) $parsed['has_parking'] : null,
            'furnished' => isset($parsed['furnished']) ? (bool) $parsed['furnished'] : null,
            'q' => isset($parsed['q']) && $parsed['q'] !== '' ? (string) $parsed['q'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function enrichWithIds(array $parsed, string $originalQuery): array
    {
        $result = [
            'original_query' => $originalQuery,
            'transaction_type' => $parsed['transaction_type'] ?? null,
            'type_id' => null,
            'type_name' => $parsed['type_name'] ?? null,
            'city_id' => null,
            'city_name' => $parsed['city_name'] ?? null,
            'quarter_name' => $parsed['quarter_name'] ?? null,
            'bedrooms' => $parsed['bedrooms'] ?? null,
            'price_max' => $parsed['price_max'] ?? null,
            'price_min' => $parsed['price_min'] ?? null,
            'surface_min' => $parsed['surface_min'] ?? null,
            'has_parking' => $parsed['has_parking'] ?? null,
            'furnished' => $parsed['furnished'] ?? null,
            'q' => $parsed['q'] ?? null,
        ];

        if (!empty($parsed['type_name'])) {
            $type = AdType::where('name', 'ilike', '%'.$parsed['type_name'].'%')->first();
            if ($type) {
                $result['type_id'] = $type->id;
                $result['type_name'] = $type->name;
            }
        }

        if (!empty($parsed['city_name'])) {
            $city = City::where('name', 'ilike', $parsed['city_name'])->first();
            if ($city) {
                $result['city_id'] = $city->id;
                $result['city_name'] = $city->name;

                if (!empty($parsed['quarter_name'])) {
                    $quarter = $city->quarters()->where('name', 'ilike', $parsed['quarter_name'])->first();
                    if ($quarter instanceof Quarter) {
                        $result['quarter_name'] = $quarter->name;
                    }
                }
            }
        }

        $hasStructured = $result['transaction_type'] || $result['type_id'] || $result['city_id']
            || $result['bedrooms'] || $result['price_max'] || $result['price_min']
            || $result['surface_min'] || $result['has_parking'] || $result['furnished'];
        if (!$hasStructured && empty($result['q'])) {
            $result['q'] = $originalQuery;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $query): array
    {
        return [
            'original_query' => $query,
            'transaction_type' => null,
            'type_id' => null,
            'type_name' => null,
            'city_id' => null,
            'city_name' => null,
            'quarter_name' => null,
            'bedrooms' => null,
            'price_max' => null,
            'price_min' => null,
            'surface_min' => null,
            'has_parking' => null,
            'furnished' => null,
            'q' => null,
        ];
    }

    private function extractJson(string $content): ?array
    {
        $content = trim($content);
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}

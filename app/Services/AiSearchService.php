<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter; // used in enrichWithIds
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
class AiSearchService
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
        'groq'     => ['url' => 'https://api.groq.com/openai/v1/chat/completions',    'config_key' => 'services.groq',     'default_model' => 'llama-3.3-70b-versatile'],
        'openai'   => ['url' => 'https://api.openai.com/v1/chat/completions',          'config_key' => 'services.openai',   'default_model' => 'gpt-4o-mini'],
        'together' => ['url' => 'https://api.together.xyz/v1/chat/completions',        'config_key' => 'services.together', 'default_model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo'],
        'mistral'  => ['url' => 'https://api.mistral.ai/v1/chat/completions',          'config_key' => 'services.mistral',  'default_model' => 'mistral-small-latest'],
    ];

    /**
     * Parse a natural language query into structured search parameters.
     *
     * @return array<string, mixed>
     */
    public function parse(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return $this->emptyResult($query);
        }

        $cacheKey = self::CACHE_PREFIX.md5($normalized);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($normalized, $query) {
            $result = $this->tryAllProviders($normalized);
            if ($result !== null) {
                return $this->enrichWithIds($result, $query);
            }

            return (new NaturalSearchRegexParser)->parse($query);
        });
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
            array_map('trim', explode(',', (string) config('services.ai_search.providers', 'groq,openai,gemini')))
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
                    'max_tokens' => 300,
                    'temperature' => 0.2,
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
                'generationConfig' => ['maxOutputTokens' => 300, 'temperature' => 0.2],
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
Tu es un assistant de recherche immobilière pour KeyHome (plateforme immobilier Afrique centrale, principalement Cameroun).

Tu dois extraire les critères de recherche d'une requête en langage naturel et renvoyer un objet JSON avec EXACTEMENT ces clés (utilise null pour les valeurs non trouvées) :

{
  "type_name": "string ou null - ex: Appartement, Maison, Villa, Studio, Terrain, Commerce",
  "city_name": "string ou null - nom de la ville",
  "quarter_name": "string ou null - nom du quartier",
  "bedrooms": "int ou null - nombre de chambres/pièces",
  "price_min": "int ou null - budget minimum en FCFA",
  "price_max": "int ou null - budget maximum en FCFA",
  "surface_min": "int ou null - surface minimum en m²",
  "has_parking": "bool ou null - true si parking/garage demandé",
  "furnished": "bool ou null - true si meublé demandé",
  "q": "string ou null - mots-clés texte pour recherche full-text si critères vagues"
}

Règles :
- Les prix sont TOUJOURS en FCFA (franc CFA). "150k" = 150000, "1.5M" = 1500000.
- "pas cher", "budget serré" → price_max bas (ex: 100000 pour location, 5000000 pour vente). "haut de gamme", "luxe" → price_min élevé.
- type_name : utilise les noms exacts des types disponibles.
- city_name et quarter_name : utilise UNIQUEMENT les noms de la liste fournie.
- bedrooms doit rester null sauf si un nombre de chambres est EXPLICITEMENT mentionné dans la requête (ex: "2 chambres", "3 pièces"). Ne jamais déduire bedrooms depuis le type de bien : un "studio" → type_name: "Studio", bedrooms: null.
- Si la requête est trop vague, mets les critères structurés à null et remplis "q" avec les mots-clés.

Référentiel disponible :
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

    private function recordGroqFailure(): void
    {
        $failures = (int) Cache::get(self::FAILURE_COUNT_KEY, 0) + 1;
        Cache::put(self::FAILURE_COUNT_KEY, $failures, self::CONTEXT_CACHE_TTL);

        if ($failures >= self::CIRCUIT_FAILURE_THRESHOLD) {
            Cache::put(self::CIRCUIT_BREAKER_KEY, true, self::CIRCUIT_OPEN_TTL);
            Log::warning('AiSearchService: circuit breaker opened after '.$failures.' consecutive failures.');
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalizeParsedResult(array $parsed): array
    {
        return [
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

        $hasStructured = $result['type_id'] || $result['city_id'] || $result['bedrooms']
            || $result['price_max'] || $result['price_min'] || $result['has_parking'] || $result['furnished'];
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

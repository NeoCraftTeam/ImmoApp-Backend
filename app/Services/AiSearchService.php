<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdType;
use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Parses natural language real-estate queries into structured search parameters using Groq LLM.
 *
 * Falls back to regex-based parsing when Groq is unavailable or returns invalid data.
 * Results are cached for 24 hours per query.
 */
class AiSearchService
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    private const CACHE_PREFIX = 'ai_search:';

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
            $result = $this->parseWithGroq($normalized);
            if ($result !== null) {
                return $this->enrichWithIds($result, $query);
            }

            return (new NaturalSearchRegexParser)->parse($query);
        });
    }

    /**
     * @return array<string, mixed>|null Returns null on failure.
     */
    private function parseWithGroq(string $query): ?array
    {
        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            Log::debug('AiSearchService: GROQ_API_KEY not configured, falling back to regex.');

            return null;
        }

        $context = $this->buildContext();

        $systemPrompt = $this->systemPrompt($context);
        $userPrompt = "Requête de l'utilisateur : \"{$query}\"\n\nRéponds UNIQUEMENT avec un objet JSON valide, sans markdown ni texte autour.";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.model', 'llama-3.3-70b-versatile'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.2,
                ]);

            if ($response->failed()) {
                Log::warning('AiSearchService: Groq API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));
            if ($content === '') {
                return null;
            }

            $decoded = $this->extractJson($content);
            if ($decoded === null || !is_array($decoded)) {
                Log::warning('AiSearchService: Invalid JSON from Groq', ['content' => substr($content, 0, 200)]);

                return null;
            }

            return $this->normalizeParsedResult($decoded);
        } catch (\Throwable $e) {
            Log::error('AiSearchService: Groq exception: '.$e->getMessage());

            return null;
        }
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
- Si la requête est trop vague, mets les critères structurés à null et remplis "q" avec les mots-clés.

Référentiel disponible :
{$context}
PROMPT;
    }

    private function buildContext(): string
    {
        $cities = City::with('quarters')->get();
        $types = AdType::all();

        $cityList = $cities->map(fn (City $c) => $c->name.': '.$c->quarters->pluck('name')->join(', '))->join("\n");
        $typeList = $types->pluck('name')->join(', ');

        return "Villes et quartiers :\n{$cityList}\n\nTypes de biens : {$typeList}";
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
                    if ($quarter) {
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

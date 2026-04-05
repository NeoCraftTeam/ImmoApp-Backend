<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdType;
use App\Models\City;
use Illuminate\Support\Facades\Cache;

/**
 * Regex-based natural language search parser.
 * Used as fallback when AI parsing is unavailable.
 */
class NaturalSearchRegexParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $query): array
    {
        $query = mb_strtolower(trim($query));
        $result = [
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

        if (preg_match('/\b(?:à louer|à la location|en location|louer|location|loue|\/mois|mensuel|par mois)\b/u', $query)) {
            $result['transaction_type'] = 'location';
        } elseif (preg_match('/\b(?:à vendre|en vente|acheter|achat|vendre|vente|acquisition)\b/u', $query)) {
            $result['transaction_type'] = 'vente';
        }

        $typeMap = [
            'studio' => ['studio'],
            'appartement' => ['appartement', 'appart', 'flat'],
            'maison' => ['maison', 'villa', 'bungalow', 'duplex'],
            'terrain' => ['terrain', 'parcelle', 'lot'],
            'commerce' => ['commerce', 'boutique', 'local commercial', 'bureau'],
        ];

        $adTypes = Cache::remember('regex_parser:ad_types', 21600, fn () => AdType::all());

        foreach ($typeMap as $typeName => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($query, $kw)) {
                    $type = $adTypes->first(fn ($t) => mb_stripos((string) $t->name, $typeName) !== false);
                    if ($type) {
                        $result['type_id'] = $type->id;
                        $result['type_name'] = $type->name;
                    }
                    break 2;
                }
            }
        }

        if (preg_match('/(\d+)\s*(?:pièces?|chambres?|ch\.?|rooms?)/u', $query, $m)) {
            $result['bedrooms'] = (int) $m[1];
        } elseif (preg_match('/1\s*pièce/u', $query)) {
            $result['bedrooms'] = 1;
        }
        // Note: "studio" maps to type_name only — no bedrooms deduced (studios have bedrooms=0 in DB)

        // Normalise written French multipliers before price extraction so
        // "50 milles francs" → "50000 francs", "2 millions fcfa" → "2000000 fcfa".
        $priceQuery = $this->normalizeWrittenMultipliers($query);

        if (preg_match('/(?:moins de|max|maximum|jusqu\'à|jusqu\'a|budget)\s*([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|francs?|f\b)?/u', $priceQuery, $m)) {
            $result['price_max'] = $this->parseAmount($m[1]);
        } elseif (preg_match('/([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|francs?)\s*(?:\/mois|par mois)?/u', $priceQuery, $m)) {
            $result['price_max'] = $this->parseAmount($m[1]);
        }

        if (preg_match('/(?:à partir de|a partir de|min|minimum|plus de|au moins)\s*([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|francs?|f\b)?/u', $priceQuery, $m)) {
            $result['price_min'] = $this->parseAmount($m[1]);
        }

        if (preg_match('/(\d+)\s*m²?/u', $query, $m)) {
            $result['surface_min'] = (int) $m[1];
        }

        if (str_contains($query, 'parking') || str_contains($query, 'garage') || str_contains($query, 'stationnement')) {
            $result['has_parking'] = true;
        }

        if (str_contains($query, 'meublé') || str_contains($query, 'meuble')) {
            $result['furnished'] = true;
        }

        $normalizedQuery = $this->removeAccents($query);
        $cities = Cache::remember('regex_parser:cities_quarters', 21600, fn () => City::with('quarters')->get());
        foreach ($cities as $city) {
            $cityNorm = $this->removeAccents(mb_strtolower((string) $city->name));
            if (str_contains($normalizedQuery, $cityNorm)) {
                $result['city_id'] = $city->id;
                $result['city_name'] = $city->name;

                foreach ($city->quarters as $quarter) {
                    $quarterNorm = $this->removeAccents(mb_strtolower((string) $quarter->name));
                    if (str_contains($normalizedQuery, $quarterNorm)) {
                        $result['quarter_name'] = $quarter->name;
                        break;
                    }
                }
                break;
            }
        }

        $hasStructured = $result['type_id'] || $result['city_id'] || $result['bedrooms']
            || $result['price_max'] || $result['price_min'] || $result['surface_min']
            || $result['has_parking'] || $result['furnished'];
        if (!$hasStructured) {
            $result['q'] = $query;
        }

        return $result;
    }

    private function removeAccents(string $str): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ç' => 'c', 'ñ' => 'n',
        ];

        return strtr($str, $map);
    }

    private function normalizeWrittenMultipliers(string $query): string
    {
        // "50 milles" → "50000", "50 mille" → "50000", "50 milliers" → "50000"
        $query = (string) preg_replace_callback(
            '/(\d+)\s*mill(?:e|es|ier|iers)\b/u',
            static fn ($m) => (string) ((int) $m[1] * 1000),
            $query
        );
        // "2 millions" → "2000000", "1 million" → "1000000"
        $query = (string) preg_replace_callback(
            '/(\d+)\s*millions?\b/u',
            static fn ($m) => (string) ((int) $m[1] * 1_000_000),
            $query
        );

        return $query;
    }

    private function parseAmount(string $raw): int
    {
        $clean = (string) preg_replace('/\s+/', '', trim($raw));

        if (str_ends_with($clean, 'k')) {
            return (int) rtrim($clean, 'k') * 1000;
        }

        return (int) preg_replace('/\D/', '', $clean);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdType;
use App\Models\City;

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

        $typeMap = [
            'appartement' => ['appartement', 'appart', 'studio', 'flat'],
            'maison' => ['maison', 'villa', 'bungalow', 'duplex'],
            'terrain' => ['terrain', 'parcelle', 'lot'],
            'commerce' => ['commerce', 'boutique', 'local commercial', 'bureau'],
        ];

        foreach ($typeMap as $typeName => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($query, $kw)) {
                    $type = AdType::where('name', 'ilike', "%{$typeName}%")->first();
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
        } elseif (preg_match('/(?:studio|1\s*pièce)/u', $query)) {
            $result['bedrooms'] = 1;
        }

        if (preg_match('/(?:moins de|max|maximum|jusqu\'à|budget)\s*([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|f)?/u', $query, $m)) {
            $result['price_max'] = $this->parseAmount($m[1]);
        } elseif (preg_match('/([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|f)\s*(?:\/mois|par mois)?/u', $query, $m)) {
            $result['price_max'] = $this->parseAmount($m[1]);
        }

        if (preg_match('/(?:à partir de|min|minimum|plus de)\s*([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|f)?/u', $query, $m)) {
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

        $cities = City::with('quarters')->get();
        foreach ($cities as $city) {
            $cityName = mb_strtolower($city->name);
            if (str_contains($query, $cityName)) {
                $result['city_id'] = $city->id;
                $result['city_name'] = $city->name;

                foreach ($city->quarters as $quarter) {
                    $quarterName = mb_strtolower($quarter->name);
                    if (str_contains($query, $quarterName)) {
                        $result['quarter_name'] = $quarter->name;
                        break;
                    }
                }
                break;
            }
        }

        $hasStructured = $result['type_id'] || $result['city_id'] || $result['bedrooms'] || $result['price_max'];
        if (!$hasStructured) {
            $result['q'] = $query;
        }

        return $result;
    }

    private function parseAmount(string $raw): int
    {
        $clean = preg_replace('/\s+/', '', $raw);
        if (str_ends_with((string) $clean, 'k')) {
            return (int) rtrim((string) $clean, 'k') * 1000;
        }

        return (int) preg_replace('/\D/', '', (string) $clean);
    }
}

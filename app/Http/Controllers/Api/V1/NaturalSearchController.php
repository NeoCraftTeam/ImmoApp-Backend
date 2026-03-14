<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\AdType;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parses a natural language query into structured search parameters.
 * No external AI API required — uses regex + keyword matching on local data.
 *
 * Example: "appartement 3 pièces à Bastos moins de 150 000 FCFA avec parking"
 * Returns: { type: "appartement", city: "Yaoundé", quarter: "Bastos", bedrooms: 3, price_max: 150000, has_parking: true }
 */
final class NaturalSearchController
{
    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:300'],
        ]);

        $query = mb_strtolower(trim((string) $data['q']));
        $result = $this->parseQuery($query);

        return response()->json($result);
    }

    /** @return array<string, mixed> */
    private function parseQuery(string $query): array
    {
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

        // --- Type detection ---
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

        // --- Bedrooms ---
        if (preg_match('/(\d+)\s*(?:pièces?|chambres?|ch\.?|rooms?)/u', $query, $m)) {
            $result['bedrooms'] = (int) $m[1];
        } elseif (preg_match('/(?:studio|1\s*pièce)/u', $query)) {
            $result['bedrooms'] = 1;
        }

        // --- Price ---
        // "moins de 150 000" / "max 150k" / "150 000 fcfa" / "150k"
        if (preg_match('/(?:moins de|max|maximum|jusqu\'à|budget)\s*([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|f)?/u', $query, $m)) {
            $result['price_max'] = $this->parseAmount($m[1]);
        } elseif (preg_match('/([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|f)\s*(?:\/mois|par mois)?/u', $query, $m)) {
            $result['price_max'] = $this->parseAmount($m[1]);
        }

        if (preg_match('/(?:à partir de|min|minimum|plus de)\s*([\d\s]+(?:k|000)?)\s*(?:fcfa|xaf|f)?/u', $query, $m)) {
            $result['price_min'] = $this->parseAmount($m[1]);
        }

        // --- Surface ---
        if (preg_match('/(\d+)\s*m²?/u', $query, $m)) {
            $result['surface_min'] = (int) $m[1];
        }

        // --- Parking ---
        if (str_contains($query, 'parking') || str_contains($query, 'garage') || str_contains($query, 'stationnement')) {
            $result['has_parking'] = true;
        }

        // --- Furnished ---
        if (str_contains($query, 'meublé') || str_contains($query, 'meuble')) {
            $result['furnished'] = true;
        }

        // --- City & Quarter detection ---
        $cities = City::with('quarters')->get();
        foreach ($cities as $city) {
            $cityName = mb_strtolower($city->name);
            if (str_contains($query, $cityName)) {
                $result['city_id'] = $city->id;
                $result['city_name'] = $city->name;

                // Try to find quarter within this city
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

        // --- Fallback: use original query as text search ---
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

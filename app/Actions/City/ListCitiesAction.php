<?php

declare(strict_types=1);

namespace App\Actions\City;

use App\Models\City;
use App\Support\GeoNameNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;

class ListCitiesAction
{
    public function handle(int $perPage = 50, ?string $search = null, ?string $countryCode = null): LengthAwarePaginator
    {
        $query = City::query()
            ->when($countryCode, fn ($builder) => $builder->where('country_code', strtoupper((string) $countryCode)))
            ->orderBy('name');

        if ($search) {
            $normalized = GeoNameNormalizer::normalize($search);
            $query->where(function ($builder) use ($search, $normalized): void {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('normalized_name', 'like', '%'.$normalized.'%');
            });

            /** @var Connection $connection */
            $connection = $query->getConnection();
            if ($connection->getDriverName() === 'pgsql') {
                $query
                    ->orderByRaw('similarity(COALESCE(normalized_name, lower(name)), ?) DESC', [$normalized])
                    ->orderByRaw("CASE place_type WHEN 'city' THEN 0 WHEN 'town' THEN 1 WHEN 'municipality' THEN 2 WHEN 'village' THEN 3 ELSE 4 END");
            }
        }

        return $query->paginate($perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\City;

use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCitiesAction
{
    public function handle(int $perPage = 50, ?string $search = null): LengthAwarePaginator
    {
        $query = City::query()->orderBy('name');

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return $query->paginate($perPage);
    }
}

<?php

namespace App\Actions\City;

use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCitiesAction
{
    public function handle(int $perPage = 10): LengthAwarePaginator
    {
        return City::query()->paginate($perPage);
    }
}

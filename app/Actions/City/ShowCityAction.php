<?php

namespace App\Actions\City;

use App\Models\City;

class ShowCityAction
{
    public function handle(int $id): ?City
    {
        return City::query()->find($id);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\City;

use App\Models\City;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateCityAction
{
    /**
     * @param  array{name:string}  $payload
     *
     * @throws Throwable
     */
    public function handle(array $payload): Model
    {
        return DB::transaction(function () use ($payload) {
            $exists = City::query()->where('name', $payload['name'])->first();
            if ($exists !== null) {
                abort(400, 'Cette ville existe déjà');
            }

            return City::query()->create($payload);
        });
    }
}

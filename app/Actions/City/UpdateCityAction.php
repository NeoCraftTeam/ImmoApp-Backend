<?php

namespace App\Actions\City;

use App\Models\City;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateCityAction
{
    /**
     * @param  array{name:string}  $payload
     *
     * @throws Throwable
     */
    public function handle(City $city, array $payload): Model
    {
        return DB::transaction(function () use ($city, $payload) {
            $exists = City::query()
                ->where('name', $payload['name'])
                ->where('id', '!=', $city->id)
                ->first();

            if ($exists !== null) {
                abort(400, 'Cette ville a déjà été modifiée');
            }

            $city->update($payload);

            return $city->refresh();
        });
    }
}

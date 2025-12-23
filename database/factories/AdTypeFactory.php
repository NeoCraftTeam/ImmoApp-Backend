<?php

namespace Database\Factories;

use App\Models\AdType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdType> */
class AdTypeFactory extends Factory
{
    protected $model = AdType::class;

    public function definition(): array
    {

        $types = [
            'chambre simple',
            'chambre meublée',
            'studio simple',
            'studio meublé',
            'appartement simple',
            'appartement meublé',
            'maison',
            'terrain',
        ];

        return [
            'name' => fake()->unique()->randomElement($types),
            'desc' => fake()->sentence(12),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

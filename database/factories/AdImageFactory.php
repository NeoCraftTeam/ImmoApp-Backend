<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<AdImage> */
class AdImageFactory extends Factory
{
    protected $model = AdImage::class;

    public function definition(): array
    {
        return [
            'image_path' => 'https://picsum.photos/640/480?random='.$this->faker->numberBetween(1, 1000).'.webp',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'ad_id' => Ad::factory(),
        ];
    }
}

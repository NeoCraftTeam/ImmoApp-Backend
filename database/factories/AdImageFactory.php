<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdImage> */
class AdImageFactory extends Factory
{
    protected $model = AdImage::class;

    public function definition(): array
    {
        return [
            'image_path' => $this->faker->imageUrl(640, 480, 'property', true, 'Image', true),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'ad_id' => Ad::factory(),
        ];
    }
}

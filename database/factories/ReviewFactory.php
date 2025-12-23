<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'ad_id' => Ad::factory(),
            'user_id' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['unlock', 'boost', 'subscription']),
            'amount' => fake()->randomFloat(2, 1, 1000),
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
            'transaction_id' => fake()->uuid(),
            'payment_method' => fake()->randomElement(['orange_money', 'mobile_money', 'stripe']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
            'ad_id' => Ad::factory(),
        ];
    }
}

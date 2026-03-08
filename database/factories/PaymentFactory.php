<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['unlock', 'boost', 'subscription']),
            'amount' => fake()->randomElement([5000, 10000, 50000, 150000]),
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
            'transaction_id' => 'KH-'.strtoupper(Str::random(12)),
            'payment_method' => fake()->randomElement(['orange_money', 'mobile_money']),
            'gateway' => 'flutterwave',
            'phone_number' => '+237'.fake()->numerify('6########'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
            'ad_id' => Ad::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function success(): static
    {
        return $this->state(['status' => 'success']);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed']);
    }

    public function flutterwave(): static
    {
        return $this->state(['gateway' => 'flutterwave']);
    }

    public function fedapay(): static
    {
        return $this->state(['gateway' => 'fedapay']);
    }
}

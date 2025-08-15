<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'avatar' => fake()->imageUrl(),
            'role' => fake()->randomElement(['admin', 'customer', 'agent']),
            'phone_number' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function agents(): Factory|UserFactory
    {
        return $this->state([
            'role' => 'agent',
            'type' => fake()->randomElement(['individual', 'agency']),
        ]);
    }

    public function admin(): Factory|UserFactory
    {
        return $this->state([
            'role' => 'admin',
            'type' => null,
        ]);
    }

    public function customers(): Factory|UserFactory
    {
        return $this->state([
            'role' => 'customer',
            'type' => null,
        ]);
    }
}

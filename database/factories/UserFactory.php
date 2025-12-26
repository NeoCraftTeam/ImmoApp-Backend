<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\City;
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
        $latitude = fake()->latitude();
        $longitude = fake()->longitude();
        $role = fake()->randomElement(UserRole::cases());

        return [
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'avatar' => fake()->imageUrl(),
            'role' => $role,
            'type' => $role === UserRole::AGENT ? fake()->randomElement(UserType::cases()) : null,
            'phone_number' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'location' => "POINT($longitude $latitude)",

            'city_id' => City::factory(),
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
            'role' => UserRole::AGENT,
            'type' => fake()->randomElement(UserType::cases()),
        ]);
    }

    public function admin(): Factory|UserFactory
    {
        return $this->state([
            'role' => UserRole::ADMIN,
            'type' => null,
        ]);
    }

    public function customers(): Factory|UserFactory
    {
        return $this->state([
            'role' => UserRole::CUSTOMER,
            'type' => null,
        ]);
    }
}

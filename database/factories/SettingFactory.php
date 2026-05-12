<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Setting> */
final class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => (string) fake()->randomNumber(3),
            'label' => fake()->words(3, true),
            'group' => fake()->randomElement(['general', 'payment', 'notification', 'seo']),
        ];
    }
}

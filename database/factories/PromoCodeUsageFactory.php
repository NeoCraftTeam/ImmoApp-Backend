<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromoCodeUsage> */
final class PromoCodeUsageFactory extends Factory
{
    protected $model = PromoCodeUsage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'promo_code_id' => PromoCode::factory(),
            'user_id' => User::factory(),
            'payment_id' => Payment::factory(),
        ];
    }
}

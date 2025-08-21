<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnlockedAd> */
class UnlockedAdFactory extends Factory
{
    protected $model = UnlockedAd::class;

    public function definition(): array
    {
        return [
            'unlocked_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'ad_id' => Ad::factory(),
            'user_id' => User::factory(),
            'payment_id' => Payment::factory(),
        ];
    }
}

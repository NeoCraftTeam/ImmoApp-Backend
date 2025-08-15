<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();
        $ads = Ad::all();

        $customers->each(function ($customer) use ($ads) {
            $adsToUnlock = $ads->random(rand(1, 3));

            foreach ($adsToUnlock as $ad) {
                $payment = Payment::factory()->create([
                    'user_id' => $customer->id,
                    'amount' => $ad->price ?? fake()->randomFloat(2, 10, 100),
                    'status' => 'success',
                    'type' => 'unlock',
                ]);

                UnlockedAd::create([
                    'user_id' => $customer->id,
                    'ad_id' => $ad->id,
                    'payment_id' => $payment->id,
                ]);
            }
        });
    }
}

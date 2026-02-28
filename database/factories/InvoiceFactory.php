<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agency = Agency::factory()->create();

        $plan = SubscriptionPlan::create([
            'name' => 'Premium',
            'slug' => 'premium-factory-'.fake()->unique()->randomNumber(5),
            'price' => 35000,
            'price_yearly' => 350000,
            'duration_days' => 30,
            'boost_score' => 25,
            'boost_duration_days' => 14,
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $subscription = Subscription::create([
            'agency_id' => $agency->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'status' => \App\Enums\SubscriptionStatus::ACTIVE,
            'amount_paid' => 35000,
            'auto_renew' => false,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $issuedAt = Carbon::now();

        return [
            'invoice_number' => Invoice::generateNumber(),
            'subscription_id' => $subscription->id,
            'agency_id' => $agency->id,
            'payment_id' => null,
            'plan_name' => 'Premium',
            'billing_period' => 'monthly',
            'amount' => 35000,
            'currency' => 'XOF',
            'issued_at' => $issuedAt,
            'period_start' => $issuedAt,
            'period_end' => $issuedAt->copy()->addDays(30),
        ];
    }
}

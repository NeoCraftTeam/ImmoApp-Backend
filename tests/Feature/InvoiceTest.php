<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();

    $this->agentUser = User::factory()->create([
        'role' => UserRole::AGENT,
        'type' => UserType::AGENCY,
        'agency_id' => $this->agency->id,
    ]);

    $this->plan = SubscriptionPlan::create([
        'name' => 'Premium',
        'slug' => 'premium-test',
        'price' => 35000,
        'price_yearly' => 350000,
        'duration_days' => 30,
        'boost_score' => 25,
        'boost_duration_days' => 14,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->subscription = Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'auto_renew' => false,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);

    $this->invoice = Invoice::create([
        'invoice_number' => Invoice::generateNumber(),
        'subscription_id' => $this->subscription->id,
        'agency_id' => $this->agency->id,
        'payment_id' => null,
        'plan_name' => 'Premium',
        'billing_period' => 'monthly',
        'amount' => 35000,
        'currency' => 'XOF',
        'issued_at' => now(),
        'period_start' => now(),
        'period_end' => now()->addDays(30),
    ]);
});

// ---------------------------------
// LIST invoices
// ---------------------------------

it('unauthenticated user cannot list invoices', function (): void {
    $this->getJson('/api/v1/invoices')
        ->assertUnauthorized();
});

it('user without agency gets 403 on invoices list', function (): void {
    $userWithoutAgency = User::factory()->create([
        'agency_id' => null,
    ]);

    Sanctum::actingAs($userWithoutAgency);

    $this->getJson('/api/v1/invoices')
        ->assertForbidden();
});

it('agent can list their agency invoices', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/invoices');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'invoice_number' => $this->invoice->invoice_number,
            'plan_name' => 'Premium',
            'currency' => 'XOF',
            'status' => 'paid',
        ]);
});

it('agent only sees invoices from their own agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherPlan = SubscriptionPlan::create([
        'name' => 'Starter',
        'slug' => 'starter-other',
        'price' => 10000,
        'duration_days' => 30,
        'boost_score' => 5,
        'boost_duration_days' => 7,
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $otherSub = Subscription::create([
        'agency_id' => $otherAgency->id,
        'subscription_plan_id' => $otherPlan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 10000,
        'auto_renew' => false,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);
    Invoice::create([
        'invoice_number' => 'KH-OTHER-001',
        'subscription_id' => $otherSub->id,
        'agency_id' => $otherAgency->id,
        'plan_name' => 'Starter',
        'billing_period' => 'monthly',
        'amount' => 10000,
        'currency' => 'XOF',
        'issued_at' => now(),
    ]);

    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/invoices');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['invoice_number' => 'KH-OTHER-001']);
});

it('invoice list response includes download_url and formatted fields', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/invoices');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'invoice_number',
                'plan_name',
                'billing_period',
                'billing_period_label',
                'amount',
                'amount_formatted',
                'currency',
                'status',
                'status_label',
                'issued_at',
                'issued_at_formatted',
                'download_url',
            ]],
        ]);
});

// ---------------------------------
// SHOW invoice
// ---------------------------------

it('unauthenticated user cannot view an invoice', function (): void {
    $this->getJson('/api/v1/invoices/'.$this->invoice->id)
        ->assertUnauthorized();
});

it('agent can view their own invoice details', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/invoices/'.$this->invoice->id);

    $response->assertSuccessful()
        ->assertJsonPath('data.invoice_number', $this->invoice->invoice_number)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.amount', 35000);
});

it('agent cannot view another agency invoice', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherPlan = SubscriptionPlan::create([
        'name' => 'Starter',
        'slug' => 'starter-test-403',
        'price' => 10000,
        'duration_days' => 30,
        'boost_score' => 5,
        'boost_duration_days' => 7,
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $otherSub = Subscription::create([
        'agency_id' => $otherAgency->id,
        'subscription_plan_id' => $otherPlan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 10000,
        'auto_renew' => false,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);
    $otherInvoice = Invoice::create([
        'invoice_number' => 'KH-403-001',
        'subscription_id' => $otherSub->id,
        'agency_id' => $otherAgency->id,
        'plan_name' => 'Starter',
        'billing_period' => 'monthly',
        'amount' => 10000,
        'currency' => 'XOF',
        'issued_at' => now(),
    ]);

    Sanctum::actingAs($this->agentUser);

    $this->getJson('/api/v1/invoices/'.$otherInvoice->id)
        ->assertForbidden();
});

it('show returns 404 for non-existent invoice', function (): void {
    Sanctum::actingAs($this->agentUser);

    $this->getJson('/api/v1/invoices/'.fake()->uuid())
        ->assertNotFound();
});

// ---------------------------------
// DOWNLOAD invoice as PDF
// ---------------------------------

it('unauthenticated user cannot download an invoice', function (): void {
    $this->getJson('/api/v1/invoices/'.$this->invoice->id.'/download')
        ->assertUnauthorized();
});

it('agent can download their invoice as a PDF', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->get('/api/v1/invoices/'.$this->invoice->id.'/download');

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('agent cannot download another agency invoice', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherPlan = SubscriptionPlan::create([
        'name' => 'Starter',
        'slug' => 'starter-download-403',
        'price' => 10000,
        'duration_days' => 30,
        'boost_score' => 5,
        'boost_duration_days' => 7,
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $otherSub = Subscription::create([
        'agency_id' => $otherAgency->id,
        'subscription_plan_id' => $otherPlan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 10000,
        'auto_renew' => false,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);
    $otherInvoice = Invoice::create([
        'invoice_number' => 'KH-DL-FORBIDDEN',
        'subscription_id' => $otherSub->id,
        'agency_id' => $otherAgency->id,
        'plan_name' => 'Starter',
        'billing_period' => 'monthly',
        'amount' => 10000,
        'currency' => 'XOF',
        'issued_at' => now(),
    ]);

    Sanctum::actingAs($this->agentUser);

    $this->get('/api/v1/invoices/'.$otherInvoice->id.'/download')
        ->assertForbidden();
});

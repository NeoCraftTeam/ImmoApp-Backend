<?php

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Promo Code Validation Endpoint
|--------------------------------------------------------------------------
*/

it('returns discount details for a valid percentage promo code', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'PROMO20',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'applicable_to' => 'all',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'PROMO20',
            'payment_type' => 'credit',
            'original_amount' => 10000,
        ]);

    $response->assertOk();
    $response->assertJsonPath('valid', true);
    $response->assertJsonPath('discount_amount', 2000);
    $response->assertJsonPath('final_amount', 8000);
});

it('returns discount details for a valid fixed promo code', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'FLAT500',
        'discount_type' => 'fixed',
        'discount_value' => 500,
        'applicable_to' => 'all',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'FLAT500',
            'payment_type' => 'subscription',
            'original_amount' => 5000,
        ]);

    $response->assertOk();
    $response->assertJsonPath('discount_amount', 500);
    $response->assertJsonPath('final_amount', 4500);
});

it('rejects an inactive promo code', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'INACTIVE',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => false,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'INACTIVE',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('valid', false);
});

it('rejects an expired promo code', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'EXPIRED',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'EXPIRED',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertUnprocessable();
});

it('rejects a promo code already used by the user', function (): void {
    $user = User::factory()->create();
    $promo = PromoCode::create([
        'code' => 'USED',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    PromoCodeUsage::create([
        'promo_code_id' => $promo->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'USED',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertUnprocessable();
});

it('rejects a promo code that has reached its max uses', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'MAXED',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'max_uses' => 5,
        'used_count' => 5,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'MAXED',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertUnprocessable();
});

it('rejects a promo code not applicable to the payment type', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'SUBONLY',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'applicable_to' => 'subscription',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'SUBONLY',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertUnprocessable();
});

it('rejects a non-existent promo code', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'DOESNOTEXIST',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertUnprocessable();
});

it('requires authentication to validate a promo code', function (): void {
    $this->postJson('/api/v1/promo-codes/validate', [
        'code' => 'TEST',
        'payment_type' => 'credit',
        'original_amount' => 1000,
    ])->assertUnauthorized();
});

it('applies a promo code as case-insensitive', function (): void {
    $user = User::factory()->create();
    PromoCode::create([
        'code' => 'CASECODE',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/promo-codes/validate', [
            'code' => 'casecode',
            'payment_type' => 'credit',
            'original_amount' => 1000,
        ])
        ->assertOk()
        ->assertJsonPath('valid', true);
});

/*
|--------------------------------------------------------------------------
| PromoCode Model Unit Tests
|--------------------------------------------------------------------------
*/

it('calculates a percentage discount correctly', function (): void {
    $promo = new PromoCode(['discount_type' => 'percentage', 'discount_value' => 25]);
    expect($promo->calculateDiscount(10000.0))->toBe(2500.0);
});

it('caps a fixed discount at the original amount', function (): void {
    $promo = new PromoCode(['discount_type' => 'fixed', 'discount_value' => 500]);
    expect($promo->calculateDiscount(300.0))->toBe(300.0);
});

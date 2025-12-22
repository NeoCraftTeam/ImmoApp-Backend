<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('a user can request a password reset link', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => __('passwords.sent')]); // ou 'Nous vous avons envoyé par email le lien...'

    // Vérifier que la notification ResetPassword a été envoyée
    Notification::assertSentTo($user, ResetPassword::class);
});

test('a user can reset password with valid token', function (): void {
    Notification::fake();
    $user = User::factory()->create([
        'password' => bcrypt('OldPassword123!'),
    ]);

    // Générer un token valide manuellement (comme si envoyé par mail)
    $token = Password::broker()->createToken($user);

    // Tenter le reset
    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => __('passwords.reset')]); // ou 'Votre mot de passe a été réinitialisé !'

    // Vérifier en base que le mot de passe a changé
    $user->refresh();
    expect(Hash::check('NewPassword456!', $user->password))->toBeTrue();
    expect(Hash::check('OldPassword123!', $user->password))->toBeFalse();
});

test('authenticated user can update password', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('CurrentPass1!'),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/update-password', [
            'current_password' => 'CurrentPass1!',
            'new_password' => 'NewSuperPass2!',
            'new_password_confirmation' => 'NewSuperPass2!',
        ]);

    $response->assertStatus(200);

    $user->refresh();
    expect(Hash::check('NewSuperPass2!', $user->password))->toBeTrue();
});

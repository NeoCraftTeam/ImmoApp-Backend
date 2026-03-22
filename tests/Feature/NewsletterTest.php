<?php

use App\Jobs\SendNewsletterCampaignJob;
use App\Mail\NewsletterBroadcastMail;
use App\Mail\NewsletterConfirmationMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Newsletter Subscribe
|--------------------------------------------------------------------------
*/

it('subscribes a new email to the newsletter', function (): void {
    Mail::fake();

    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'test@example.com',
        'name' => 'Jean Dupont',
        'locale' => 'fr',
    ]);

    $response->assertCreated();
    expect(NewsletterSubscriber::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('sends a confirmation email on new subscription', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'confirm@example.com',
        'name' => 'Marie Test',
    ])->assertCreated();

    Mail::assertSent(NewsletterConfirmationMail::class, fn ($mail): bool => $mail->subscriber->email === 'confirm@example.com');
});

it('auto-confirms the subscriber on creation', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'autoconfirm@example.com',
    ])->assertCreated();

    $subscriber = NewsletterSubscriber::where('email', 'autoconfirm@example.com')->first();
    expect($subscriber->confirmed_at)->not->toBeNull();
    expect($subscriber->isSubscribed())->toBeTrue();
});

it('returns 201 when re-subscribing an existing email', function (): void {
    Mail::fake();

    NewsletterSubscriber::create([
        'email' => 'existing@example.com',
        'token' => hash('sha256', 'dummy'),
        'confirmed_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'existing@example.com',
    ]);

    $response->assertCreated();
    expect(NewsletterSubscriber::where('email', 'existing@example.com')->count())->toBe(1);

    Mail::assertNothingSent();
});

it('re-activates an unsubscribed email and sends confirmation', function (): void {
    Mail::fake();

    $subscriber = NewsletterSubscriber::create([
        'email' => 'unsub@example.com',
        'token' => hash('sha256', 'dummy2'),
        'confirmed_at' => now()->subMonth(),
        'unsubscribed_at' => now()->subDays(5),
    ]);

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'unsub@example.com'])
        ->assertCreated();

    expect($subscriber->fresh()->unsubscribed_at)->toBeNull();
    expect($subscriber->fresh()->confirmed_at)->not->toBeNull();

    Mail::assertSent(NewsletterConfirmationMail::class);
});

it('rejects an invalid email address', function (): void {
    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'not-an-email'])
        ->assertUnprocessable();
});

it('rejects subscription without an email', function (): void {
    $this->postJson('/api/v1/newsletter/subscribe', [])
        ->assertUnprocessable();
});

it('rejects invalid locale values', function (): void {
    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'user@example.com',
        'locale' => 'de',
    ])->assertUnprocessable();
});

it('stores the source field when provided', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'source@example.com',
        'source' => 'landing_page',
    ])->assertCreated();

    $subscriber = NewsletterSubscriber::where('email', 'source@example.com')->first();
    expect($subscriber->source)->toBe('landing_page');
});

it('normalizes email to lowercase', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'Test@EXAMPLE.COM',
    ])->assertCreated();

    expect(NewsletterSubscriber::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('generates a unique token on creation', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'token@example.com',
    ])->assertCreated();

    $subscriber = NewsletterSubscriber::where('email', 'token@example.com')->first();
    expect($subscriber->token)->not->toBeNull()->toHaveLength(64);
});

/*
|--------------------------------------------------------------------------
| Newsletter Unsubscribe
|--------------------------------------------------------------------------
*/

it('unsubscribes using a valid token', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'tounsubscribe@example.com',
        'token' => hash('sha256', 'valid-token'),
        'confirmed_at' => now(),
    ]);

    $this->getJson("/api/v1/newsletter/unsubscribe/{$subscriber->token}")
        ->assertOk();

    expect($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
});

it('returns 404 for an invalid unsubscribe token', function (): void {
    $this->getJson('/api/v1/newsletter/unsubscribe/invalid-token-xyz')
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Newsletter Campaign Job
|--------------------------------------------------------------------------
*/

it('sends broadcast emails only to active subscribers', function (): void {
    Mail::fake();

    $active1 = NewsletterSubscriber::factory()->create();
    $active2 = NewsletterSubscriber::factory()->create();
    NewsletterSubscriber::factory()->unsubscribed()->create();
    NewsletterSubscriber::factory()->unconfirmed()->create();

    $campaign = NewsletterCampaign::factory()->create([
        'subject' => 'Weekly Deals',
        'body' => '<p>Great properties this week!</p>',
    ]);

    new SendNewsletterCampaignJob($campaign)->handle();

    Mail::assertSent(NewsletterBroadcastMail::class, 2);
    Mail::assertSent(NewsletterBroadcastMail::class, fn ($mail): bool => $mail->subscriber->id === $active1->id);
    Mail::assertSent(NewsletterBroadcastMail::class, fn ($mail): bool => $mail->subscriber->id === $active2->id);

    expect($campaign->fresh()->sent_at)->not->toBeNull();
    expect($campaign->fresh()->recipients_count)->toBe(2);
});

it('marks campaign as sent with correct count', function (): void {
    Mail::fake();

    NewsletterSubscriber::factory()->count(5)->create();

    $campaign = NewsletterCampaign::factory()->create();

    new SendNewsletterCampaignJob($campaign)->handle();

    $campaign->refresh();
    expect($campaign->isSent())->toBeTrue();
    expect($campaign->recipients_count)->toBe(5);
});

it('handles campaign with zero subscribers gracefully', function (): void {
    Mail::fake();

    $campaign = NewsletterCampaign::factory()->create();

    new SendNewsletterCampaignJob($campaign)->handle();

    Mail::assertNothingQueued();
    expect($campaign->fresh()->recipients_count)->toBe(0);
    expect($campaign->fresh()->sent_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Newsletter Models
|--------------------------------------------------------------------------
*/

it('correctly determines subscriber status', function (): void {
    $activeSubscriber = NewsletterSubscriber::factory()->create();
    expect($activeSubscriber->isSubscribed())->toBeTrue();

    $unconfirmed = NewsletterSubscriber::factory()->unconfirmed()->create();
    expect($unconfirmed->isSubscribed())->toBeFalse();

    $unsubscribed = NewsletterSubscriber::factory()->unsubscribed()->create();
    expect($unsubscribed->isSubscribed())->toBeFalse();
});

it('correctly determines campaign sent status', function (): void {
    $draft = NewsletterCampaign::factory()->create();
    expect($draft->isSent())->toBeFalse();

    $sent = NewsletterCampaign::factory()->sent()->create();
    expect($sent->isSent())->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Channels\SmsChannel;
use App\Channels\WhatsAppChannel;
use App\Models\NotificationPreference;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Notifications\ViewingReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fake all external HTTP so no real network calls occur.
 * Orange token + SMS endpoint, Twilio, WhatsApp Graph API.
 */
function fakeSmsAndWaHttp(): void
{
    Http::fake([
        'api.orange.com/oauth/*' => Http::response(['access_token' => 'fake-token'], 200),
        'api.orange.com/smsmessaging/*' => Http::response([], 201),
        'api.twilio.com/*' => Http::response(['sid' => 'SM_fake'], 201),
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.fake']]], 200),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Item 3 — SmsChannel
// ─────────────────────────────────────────────────────────────────────────────

it('SmsChannel skips send when SMS is disabled', function (): void {
    Http::fake();
    config(['services.sms.enabled' => false]);

    $user = User::factory()->create(['phone_number' => '+237699000001']);
    $channel = app(SmsChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toSms(mixed $notifiable): string { return 'Test SMS'; }
    };

    $channel->send($user, $notif);

    Http::assertNothingSent();
});

it('SmsChannel skips send when user has no phone number', function (): void {
    fakeSmsAndWaHttp();
    config(['services.sms.enabled' => true, 'services.sms.provider' => 'twilio',
        'services.sms.twilio.sid' => 'AC_fake', 'services.sms.twilio.token' => 'fake',
        'services.sms.twilio.from' => '+15005550006']);

    $user = User::factory()->create(['phone_number' => null]);
    $channel = app(SmsChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toSms(mixed $notifiable): string { return 'Test SMS'; }
    };

    $channel->send($user, $notif);

    Http::assertNothingSent();
});

it('SmsChannel skips send when user has sms_enabled=false', function (): void {
    fakeSmsAndWaHttp();
    config(['services.sms.enabled' => true, 'services.sms.provider' => 'twilio',
        'services.sms.twilio.sid' => 'AC_fake', 'services.sms.twilio.token' => 'fake',
        'services.sms.twilio.from' => '+15005550006']);

    $user = User::factory()->create(['phone_number' => '+237699000002']);
    NotificationPreference::create(['user_id' => $user->id, 'sms_enabled' => false]);

    $channel = app(SmsChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toSms(mixed $notifiable): string { return 'Test SMS'; }
    };

    $channel->send($user, $notif);

    Http::assertNothingSent();
});

it('SmsChannel sends via Twilio when enabled and user has phone', function (): void {
    fakeSmsAndWaHttp();
    config([
        'services.sms.enabled' => true,
        'services.sms.provider' => 'twilio',
        'services.sms.twilio.sid' => 'AC_fake',
        'services.sms.twilio.token' => 'fake_token',
        'services.sms.twilio.from' => '+15005550006',
        'services.sms.twilio.api_url' => 'https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json',
    ]);

    $user = User::factory()->create(['phone_number' => '+237699000003']);
    $channel = app(SmsChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toSms(mixed $notifiable): string { return 'Test SMS'; }
    };

    $channel->send($user, $notif);

    Http::assertSentCount(1);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'twilio.com'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Item 4 — WhatsAppChannel
// ─────────────────────────────────────────────────────────────────────────────

it('WhatsAppChannel skips send when WhatsApp is disabled', function (): void {
    Http::fake();
    config(['services.whatsapp.enabled' => false]);

    $user = User::factory()->create(['phone_number' => '+237699000004', 'phone_is_whatsapp' => true]);
    $channel = app(WhatsAppChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toWhatsApp(mixed $notifiable): array { return ['body' => 'Hello WA']; }
    };

    $channel->send($user, $notif);

    Http::assertNothingSent();
});

it('WhatsAppChannel skips send when phone_is_whatsapp is false', function (): void {
    Http::fake();
    config(['services.whatsapp.enabled' => true]);

    $user = User::factory()->create(['phone_number' => '+237699000005', 'phone_is_whatsapp' => false]);
    $channel = app(WhatsAppChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toWhatsApp(mixed $notifiable): array { return ['body' => 'Hello WA']; }
    };

    $channel->send($user, $notif);

    Http::assertNothingSent();
});

it('WhatsAppChannel sends text via Meta Graph API when enabled', function (): void {
    fakeSmsAndWaHttp();
    config([
        'services.whatsapp.enabled' => true,
        'services.whatsapp.token' => 'EAAx_fake',
        'services.whatsapp.phone_number_id' => '123456789',
        'services.whatsapp.api_version' => 'v19.0',
        'services.whatsapp.api_url' => 'https://graph.facebook.com',
    ]);

    $user = User::factory()->create(['phone_number' => '+237699000006', 'phone_is_whatsapp' => true]);
    $channel = app(WhatsAppChannel::class);

    $notif = new class extends \Illuminate\Notifications\Notification {
        public function toWhatsApp(mixed $notifiable): array { return ['body' => 'Hello WA']; }
    };

    $channel->send($user, $notif);

    Http::assertSentCount(1);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'graph.facebook.com'));
});

// ─────────────────────────────────────────────────────────────────────────────
// Item 3+4 — ViewingReminderNotification includes channels in via()
// ─────────────────────────────────────────────────────────────────────────────

it('ViewingReminderNotification includes SmsChannel when SMS enabled and user has phone', function (): void {
    config(['services.sms.enabled' => true, 'services.whatsapp.enabled' => false]);

    Carbon::setTestNow(Carbon::parse('2026-06-12 08:00:00'));

    $user = User::factory()->create([
        'phone_number' => '+237699000008',
        'phone_is_whatsapp' => false,
    ]);

    $reservation = null;
    \App\Models\Ad::withoutSyncingToSearch(function () use (&$reservation, $user): void {
        $ad = \App\Models\Ad::factory()->create(['user_id' => $user->id]);
        $reservation = TentativeReservation::factory()->create([
            'client_id' => $user->id,
            'ad_id' => $ad->id,
            'slot_date' => now()->addDay(),
        ]);
    });

    $notification = new ViewingReminderNotification($reservation);
    $channels = $notification->via($user);

    expect($channels)->toContain(SmsChannel::class)
        ->and($channels)->not->toContain(WhatsAppChannel::class);
});

it('ViewingReminderNotification includes WhatsAppChannel when WA enabled and phone_is_whatsapp', function (): void {
    config(['services.sms.enabled' => false, 'services.whatsapp.enabled' => true]);

    Carbon::setTestNow(Carbon::parse('2026-06-12 08:00:00'));

    $user = User::factory()->create([
        'phone_number' => '+237699000009',
        'phone_is_whatsapp' => true,
    ]);

    $reservation = null;
    \App\Models\Ad::withoutSyncingToSearch(function () use (&$reservation, $user): void {
        $ad = \App\Models\Ad::factory()->create(['user_id' => $user->id]);
        $reservation = TentativeReservation::factory()->create([
            'client_id' => $user->id,
            'ad_id' => $ad->id,
            'slot_date' => now()->addDay(),
        ]);
    });

    $notification = new ViewingReminderNotification($reservation);
    $channels = $notification->via($user);

    expect($channels)->toContain(WhatsAppChannel::class)
        ->and($channels)->not->toContain(SmsChannel::class);
});

it('ViewingReminderNotification toSms returns a non-empty string', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-12 08:00:00'));

    $user = User::factory()->create(['phone_number' => '+237699000010']);

    $reservation = null;
    \App\Models\Ad::withoutSyncingToSearch(function () use (&$reservation, $user): void {
        $ad = \App\Models\Ad::factory()->create(['user_id' => $user->id, 'title' => 'Villa Rose']);
        $reservation = TentativeReservation::factory()->create([
            'client_id' => $user->id,
            'ad_id' => $ad->id,
            'slot_date' => now()->addDay(),
        ]);
    });

    $sms = (new ViewingReminderNotification($reservation))->toSms($user);

    expect($sms)->toContain('KeyHome')
        ->toContain('Villa Rose')
        ->not->toBeEmpty();
});

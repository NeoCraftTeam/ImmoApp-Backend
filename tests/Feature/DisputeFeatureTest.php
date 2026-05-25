<?php

declare(strict_types=1);

use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\User;
use App\Notifications\DisputeMessageReceivedNotification;
use App\Notifications\DisputeOpenedNotification;
use App\Notifications\DisputeStatusChangedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('opens a dispute and notifies the respondent + admins (not the initiator)', function (): void {
    Notification::fake();

    $initiator = User::factory()->customers()->create();
    $respondent = User::factory()->agents()->create();
    User::factory()->admin()->create();

    $response = $this
        ->actingAs($initiator, 'sanctum')
        ->postJson('/api/v1/disputes', [
            'type' => DisputeType::DEPOSIT->value,
            'respondent_id' => $respondent->id,
            'title' => 'Caution non restituée',
            'description' => 'Le bailleur refuse de me restituer ma caution malgré l\'état des lieux conforme.',
            'amount_claimed' => 250000,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', DisputeStatus::OPEN->value)
        ->assertJsonPath('data.type', DisputeType::DEPOSIT->value);

    expect(Dispute::query()->count())->toBe(1);

    Notification::assertSentTo($respondent, DisputeOpenedNotification::class);
    Notification::assertNotSentTo($initiator, DisputeOpenedNotification::class);
});

it('rejects a dispute against oneself', function (): void {
    $user = User::factory()->customers()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/disputes', [
            'type' => DisputeType::OTHER->value,
            'respondent_id' => $user->id,
            'title' => 'Self dispute',
            'description' => 'Je dépose un litige contre moi-même pour test.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['respondent_id']);
});

it('forbids a non-party to view someone else\'s dispute (IDOR protection)', function (): void {
    $dispute = Dispute::factory()->create();
    $stranger = User::factory()->customers()->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/disputes/{$dispute->id}")
        ->assertForbidden();
});

it('allows the initiator to read their own dispute', function (): void {
    $dispute = Dispute::factory()->create();

    $this->actingAs($dispute->initiator, 'sanctum')
        ->getJson("/api/v1/disputes/{$dispute->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $dispute->id);
});

it('allows the respondent to post a message and notifies the initiator', function (): void {
    Notification::fake();
    $dispute = Dispute::factory()->create();

    $this->actingAs($dispute->respondent, 'sanctum')
        ->postJson("/api/v1/disputes/{$dispute->id}/messages", [
            'body' => 'Bonjour, je conteste cette réclamation.',
        ])
        ->assertCreated();

    expect(DisputeMessage::query()->where('dispute_id', $dispute->id)->count())->toBe(1);

    Notification::assertSentTo($dispute->initiator, DisputeMessageReceivedNotification::class);
    Notification::assertNotSentTo($dispute->respondent, DisputeMessageReceivedNotification::class);
});

it('blocks messages on a closed dispute (policy-level)', function (): void {
    $dispute = Dispute::factory()->resolved()->create();

    $this->actingAs($dispute->initiator, 'sanctum')
        ->postJson("/api/v1/disputes/{$dispute->id}/messages", [
            'body' => 'Trop tard',
        ])
        ->assertForbidden();
});

it('lets a non-admin party post but coerces is_internal to false', function (): void {
    Notification::fake();
    $dispute = Dispute::factory()->create();

    $this->actingAs($dispute->initiator, 'sanctum')
        ->postJson("/api/v1/disputes/{$dispute->id}/messages", [
            'body' => 'Tentative de note interne',
            'is_internal' => true,
        ])
        ->assertCreated();

    $message = DisputeMessage::query()->where('dispute_id', $dispute->id)->latest()->first();
    expect($message->is_internal)->toBeFalse();
});

it('allows uploading evidence on an open dispute', function (): void {
    Storage::fake('public');
    $dispute = Dispute::factory()->create();

    $this->actingAs($dispute->initiator, 'sanctum')
        ->postJson("/api/v1/disputes/{$dispute->id}/evidences", [
            'type' => 'photo',
            'file' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertCreated();

    expect($dispute->evidences()->count())->toBe(1);
});

it('rejects evidence upload from a stranger', function (): void {
    Storage::fake('public');
    $dispute = Dispute::factory()->create();
    $stranger = User::factory()->customers()->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/disputes/{$dispute->id}/evidences", [
            'type' => 'photo',
            'file' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertForbidden();
});

it('lets an admin transition open → under_review and notifies both parties', function (): void {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $dispute = Dispute::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/disputes/{$dispute->id}/status", [
            'status' => DisputeStatus::UNDER_REVIEW->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', DisputeStatus::UNDER_REVIEW->value);

    Notification::assertSentTo($dispute->initiator, DisputeStatusChangedNotification::class);
    Notification::assertSentTo($dispute->respondent, DisputeStatusChangedNotification::class);
});

it('forbids non-admin from transitioning status', function (): void {
    $dispute = Dispute::factory()->create();

    $this->actingAs($dispute->initiator, 'sanctum')
        ->patchJson("/api/v1/disputes/{$dispute->id}/status", [
            'status' => DisputeStatus::UNDER_REVIEW->value,
        ])
        ->assertForbidden();
});

it('rejects forbidden state transitions', function (): void {
    $admin = User::factory()->admin()->create();
    $dispute = Dispute::factory()->create(); // OPEN

    // OPEN → MEDIATION is forbidden (must go through UNDER_REVIEW first).
    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/disputes/{$dispute->id}/status", [
            'status' => DisputeStatus::MEDIATION->value,
        ])
        ->assertStatus(422);
});

it('marks resolved_at and resolution_note when admin resolves a mediation', function (): void {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $dispute = Dispute::factory()->mediation()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/disputes/{$dispute->id}/status", [
            'status' => DisputeStatus::RESOLVED_AMICABLY->value,
            'resolution_note' => 'Accord mutuel signé.',
        ])
        ->assertOk();

    $dispute->refresh();
    expect($dispute->status)->toBe(DisputeStatus::RESOLVED_AMICABLY)
        ->and($dispute->resolved_at)->not->toBeNull()
        ->and($dispute->resolution_note)->toBe('Accord mutuel signé.')
        ->and($dispute->admin_id)->toBe($admin->id);
});

it('lists only the disputes the user is involved in (non-admin)', function (): void {
    $alice = User::factory()->customers()->create();
    Dispute::factory()->create(['initiator_id' => $alice->id]);
    Dispute::factory()->create(['respondent_id' => $alice->id]);
    Dispute::factory()->count(3)->create(); // disputes that don't involve Alice

    $response = $this->actingAs($alice, 'sanctum')->getJson('/api/v1/disputes');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('lists all disputes for an admin', function (): void {
    $admin = User::factory()->admin()->create();
    Dispute::factory()->count(3)->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/disputes');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('generates a unique reference following the KH-LITIGE pattern', function (): void {
    $initiator = User::factory()->customers()->create();
    $respondent = User::factory()->agents()->create();

    $this->actingAs($initiator, 'sanctum')
        ->postJson('/api/v1/disputes', [
            'type' => DisputeType::DEPOSIT->value,
            'respondent_id' => $respondent->id,
            'title' => 'Caution',
            'description' => 'Description suffisamment longue pour la validation min:20.',
        ])
        ->assertCreated();

    $reference = Dispute::query()->value('reference');
    expect($reference)->toMatch('/^KH-LITIGE-\d{4}-[A-Z0-9]{6}$/');
});

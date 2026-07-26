<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Chat — Private Conversation Channel
|--------------------------------------------------------------------------
| Only the two participants (tenant + landlord) of a conversation may
| subscribe. Returns 404-style false (not 403) to prevent IDOR enumeration.
*/
Broadcast::channel('conversation.{uuid}', function (User $user, string $uuid): bool {
    $conv = Conversation::where('id', $uuid)->first();

    if ($conv === null) {
        return false;
    }

    return in_array($user->id, [$conv->tenant_id, $conv->landlord_id], true);
});

/*
|--------------------------------------------------------------------------
| User private channel — reservations & notifications
|--------------------------------------------------------------------------
| Each authenticated user subscribes to their own private channel.
| Used by ReservationStatusChanged and future per-user events.
*/
Broadcast::channel('user.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

/*
|--------------------------------------------------------------------------
| Chat — Presence Channel (online status)
|--------------------------------------------------------------------------
| Canal de présence global consommé par l'indicateur « en ligne » du web
| (GlobalPresenceChannel + usePresence). Le payload NE DIFFUSE PLUS le nom
| ni l'avatar : ils étaient exposés à tout utilisateur connecté (fuite de
| vie privée) alors que les clients ne lisent que l'`id` et le `device`.
| On n'expose donc que ces deux champs — présence préservée, PII retirée.
*/
Broadcast::channel('online-users', function (User $user): array {
    $ua = (string) request()->header('User-Agent', '');
    $isMobile = (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua);

    return [
        'id' => $user->id,
        'device' => $isMobile ? 'mobile' : 'desktop',
    ];
});

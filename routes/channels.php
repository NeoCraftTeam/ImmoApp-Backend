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
| Chat — Presence Channel (online/offline status)
|--------------------------------------------------------------------------
*/
Broadcast::channel('online-users', function (User $user): array {
    $ua = (string) request()->header('User-Agent', '');
    $isMobile = (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua);

    return [
        'id' => $user->id,
        'name' => trim("{$user->firstname} {$user->lastname}"),
        'avatar' => $user->avatar,
        'device' => $isMobile ? 'mobile' : 'desktop',
    ];
});

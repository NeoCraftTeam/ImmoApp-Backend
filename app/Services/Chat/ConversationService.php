<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Events\Chat\MessageRead;
use App\Exceptions\Chat\ConversationNotAllowedException;
use App\Models\Conversation;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Manages conversation lifecycle: creation, listing, read-receipts, and archiving.
 */
final readonly class ConversationService
{
    /**
     * Idempotently find or create a conversation for a given ad+tenant pair.
     *
     * @throws ConversationNotAllowedException if the tenant has not unlocked the ad
     */
    public function findOrCreate(string $adId, string $tenantId, string $landlordId): Conversation
    {
        $unlocked = UnlockedAd::where('ad_id', $adId)
            ->where('user_id', $tenantId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $unlocked) {
            throw new ConversationNotAllowedException();
        }

        return Conversation::firstOrCreate(
            ['ad_id' => $adId, 'tenant_id' => $tenantId],
            ['landlord_id' => $landlordId, 'status' => ConversationStatus::Active],
        );
    }

    /**
     * Return paginated conversations for a user, ordered by last activity.
     *
     * @return LengthAwarePaginator<Conversation>
     */
    public function getConversationsForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Conversation::forUser($user->id)
            ->with([
                'latestMessage',
                'ad:id,title',
                'tenant:id,firstname,lastname,avatar',
                'landlord:id,firstname,lastname,avatar',
            ])
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    /**
     * Mark all unread messages in a conversation as read for the given user,
     * update the last-read timestamp, and broadcast the read event.
     */
    public function markAsRead(Conversation $conv, User $reader): void
    {
        DB::transaction(function () use ($conv, $reader): void {
            $now = now();

            if ($reader->id === $conv->tenant_id) {
                $conv->update(['tenant_last_read_at' => $now]);
            } else {
                $conv->update(['landlord_last_read_at' => $now]);
            }

            $conv->messages()
                ->where('sender_id', '!=', $reader->id)
                ->whereIn('status', [MessageStatus::Sent->value, MessageStatus::Delivered->value])
                ->update(['status' => MessageStatus::Read->value, 'read_at' => $now]);
        });

        $this->invalidateUnreadCache($reader->id);

        broadcast(new MessageRead($conv->id, $reader->id, now()->toIso8601String()));
    }

    /**
     * Archive a conversation. Only participants may archive.
     */
    public function archive(Conversation $conv, User $user): void
    {
        abort_unless(
            in_array($user->id, [$conv->tenant_id, $conv->landlord_id], true),
            404,
        );

        $conv->update(['status' => ConversationStatus::Archived]);
    }

    /**
     * Return unread message counts per conversation for a user.
     * Result is cached in Redis for 30 seconds.
     *
     * @return array{total: int, conversations: list<array{uuid: string, count: int}>}
     */
    public function getUnreadCount(User $user): array
    {
        $cacheKey = "unread:{$user->id}";

        /** @var array{total: int, conversations: list<array{uuid: string, count: int}>}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $conversations = Conversation::forUser($user->id)
            ->active()
            ->get(['id', 'tenant_id', 'tenant_last_read_at', 'landlord_last_read_at']);

        $items = [];
        $total = 0;

        foreach ($conversations as $conv) {
            $count = $conv->unreadCountFor($user);
            if ($count > 0) {
                $items[] = ['uuid' => $conv->id, 'count' => $count];
                $total += $count;
            }
        }

        $result = ['total' => $total, 'conversations' => $items];

        Cache::put($cacheKey, $result, 30);

        return $result;
    }

    /** Invalidate the unread count cache for a user. */
    public function invalidateUnreadCache(string $userId): void
    {
        Cache::forget("unread:{$userId}");
    }
}

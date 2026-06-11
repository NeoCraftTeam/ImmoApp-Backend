<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Events\Chat\ConversationArchived;
use App\Events\Chat\ConversationUnarchived;
use App\Events\Chat\MessageRead;
use App\Exceptions\Chat\ConversationNotAllowedException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Support\ChatE2eeSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
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
     * Semantics:
     *   - If a conversation already exists for this (ad, tenant) pair, return it
     *     immediately. The unlock requirement is only enforced when a brand-new
     *     conversation needs to be created. This is intentional: once a
     *     conversation has been started, it must not break because the original
     *     unlock row was soft-deleted (refund, expiration, admin action, …).
     *     The relationship has already been "paid for".
     *   - If no conversation exists yet, the tenant MUST have an active
     *     UnlockedAd row for the ad — otherwise we throw
     *     ConversationNotAllowedException so the caller can prompt for unlock.
     *   - If the conversation was previously archived, we reactivate it so the
     *     tenant can resume the chat from the ad detail page.
     *
     * @throws ConversationNotAllowedException if the tenant has not unlocked the ad
     */
    public function findOrCreate(string $adId, string $tenantId, string $landlordId): Conversation
    {
        // Refuse self-conversation: a landlord browsing his own ad must not
        // be able to start a chat with himself (no business purpose, would
        // produce broken UI states everywhere).
        if ($tenantId === $landlordId) {
            throw new ConversationNotAllowedException;
        }

        $existing = Conversation::query()
            ->where('ad_id', $adId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing !== null) {
            // Reopen archived conversations on a fresh "Send a message" click.
            if ($existing->status === ConversationStatus::Archived) {
                $existing->update(['status' => ConversationStatus::Active]);
            }

            return $existing;
        }

        $unlocked = UnlockedAd::query()
            ->where('ad_id', $adId)
            ->where('user_id', $tenantId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$unlocked) {
            throw new ConversationNotAllowedException;
        }

        // Race-safe creation: a concurrent POST /conversations from the same
        // tenant for the same ad may sneak between our SELECT and INSERT.
        // The unique index on (ad_id, tenant_id) is the source of truth — on
        // collision, fall back to returning the row that just won the race.
        try {
            return Conversation::create([
                'ad_id' => $adId,
                'tenant_id' => $tenantId,
                'landlord_id' => $landlordId,
                'status' => ConversationStatus::Active,
            ]);
        } catch (UniqueConstraintViolationException) {
            $winner = Conversation::query()
                ->where('ad_id', $adId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($winner === null) {
                throw new ConversationNotAllowedException;
            }

            if ($winner->status === ConversationStatus::Archived) {
                $winner->update(['status' => ConversationStatus::Active]);
            }

            return $winner;
        }
    }

    /**
     * Return paginated conversations for a user, ordered by last activity.
     * Uses a subquery to compute unread_count in a single query (avoids N+1).
     *
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function getConversationsForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $userId = $user->id;

        $paginator = Conversation::forUser($userId)
            ->with([
                'ad' => function ($query): void {
                    $query->select('id', 'title', 'slug')
                        ->with(['media' => function ($mediaQuery): void {
                            $mediaQuery->where('collection_name', 'images')
                                ->orderBy('order_column');
                        }]);
                },
                'tenant' => function ($query): void {
                    $query->select(...ChatE2eeSchema::userParticipantSelectColumns())
                        ->with(['media' => function ($mediaQuery): void {
                            $mediaQuery->where('collection_name', 'avatars')
                                ->orderBy('order_column');
                        }]);
                },
                'landlord' => function ($query): void {
                    $query->select(...ChatE2eeSchema::userParticipantSelectColumns())
                        ->with(['media' => function ($mediaQuery): void {
                            $mediaQuery->where('collection_name', 'avatars')
                                ->orderBy('order_column');
                        }]);
                },
            ])
            ->withCount(['messages as computed_unread_count' => function ($q) use ($userId): void {
                // "My" last-read timestamp depends on whether I'm the tenant or the
                // landlord on each row; bind $userId rather than interpolate it.
                $lastReadCase = 'CASE WHEN conversations.tenant_id = ? '
                    .'THEN conversations.tenant_last_read_at '
                    .'ELSE conversations.landlord_last_read_at END';

                $q->where('sender_id', '!=', $userId)
                    ->whereRaw(
                        "({$lastReadCase} IS NULL OR messages.created_at > {$lastReadCase})",
                        [$userId, $userId],
                    );
            }])
            ->orderByDesc('last_message_at')
            ->paginate($perPage);

        $this->attachPreviewMessagesToConversations($paginator->getCollection());

        return $paginator;
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

        try {
            broadcast(new MessageRead($conv->id, $reader->id, now()->toIso8601String()))
                ->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }
    }

    /**
     * Archive a conversation. Only participants may archive.
     * Idempotent: no-op if already archived.
     *
     * Broadcasts ConversationArchived (toOthers) so the other participant's
     * UI updates in real time without polling.
     */
    public function archive(Conversation $conv, User $user): void
    {
        abort_unless($user->id === $conv->tenant_id || $user->id === $conv->landlord_id, 404);

        if ($conv->status === ConversationStatus::Archived) {
            return;
        }

        $conv->update(['status' => ConversationStatus::Archived]);

        try {
            broadcast(new ConversationArchived($conv->id, $user->id))->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }
    }

    /**
     * Restore an archived conversation to active. Participants only.
     * Idempotent: no-op if already active. Broadcasts to the other participant.
     */
    public function unarchive(Conversation $conv, User $user): void
    {
        abort_unless($user->id === $conv->tenant_id || $user->id === $conv->landlord_id, 404);

        if ($conv->status === ConversationStatus::Active) {
            return;
        }

        if ($conv->status !== ConversationStatus::Archived) {
            abort(422, 'Only archived conversations can be restored.');
        }

        $conv->update(['status' => ConversationStatus::Active]);

        try {
            broadcast(new ConversationUnarchived($conv->id, $user->id))->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }
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

        // Single SQL aggregation — replaces the previous N+1 loop that ran one
        // COUNT(*) per conversation. Joins messages once, picks the correct
        // last-read timestamp per row via CASE, groups by conversation id.
        $userId = $user->id;
        $rows = DB::table('conversations')
            ->join('messages', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.status', ConversationStatus::Active->value)
            ->where(function ($q) use ($userId): void {
                $q->where('conversations.tenant_id', $userId)
                    ->orWhere('conversations.landlord_id', $userId);
            })
            ->where('messages.sender_id', '!=', $userId)
            ->whereNull('messages.deleted_at')
            ->whereRaw(
                "messages.created_at > COALESCE(
                    CASE WHEN conversations.tenant_id = ? THEN conversations.tenant_last_read_at
                         ELSE conversations.landlord_last_read_at END,
                    '1970-01-01'::timestamp
                )",
                [$userId]
            )
            ->groupBy('conversations.id')
            ->select('conversations.id as uuid', DB::raw('COUNT(*) as count'))
            ->get();

        $items = $rows->map(fn ($r) => ['uuid' => (string) $r->uuid, 'count' => (int) $r->count])->values()->all();
        $total = (int) $rows->sum('count');

        $result = ['total' => $total, 'conversations' => $items];

        Cache::put($cacheKey, $result, 30);

        return $result;
    }

    /**
     * Load the true latest non-deleted row for ConversationResource previews (dynamic relation `previewMessage`).
     * Laravel `latestOfMany` is not used — PostgreSQL has no aggregate MAX(uuid).
     */
    public function attachPreviewMessage(Conversation $conv): void
    {
        $msg = Message::query()
            ->where('conversation_id', $conv->id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $conv->setRelation('previewMessage', $msg);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Conversation>  $conversations
     */
    private function attachPreviewMessagesToConversations(\Illuminate\Database\Eloquent\Collection $conversations): void
    {
        if ($conversations->isEmpty()) {
            return;
        }

        /** @var Collection<int, string> $ids */
        $ids = $conversations->pluck('id')->values();
        $byConv = $this->loadPreviewMessagesKeyedByConversation($ids);

        $conversations->each(function (Conversation $c) use ($byConv): void {
            $c->setRelation('previewMessage', $byConv->get($c->id));
        });
    }

    /**
     * @param  Collection<int, string>  $conversationIds
     * @return Collection<string, Message>
     */
    private function loadPreviewMessagesKeyedByConversation(Collection $conversationIds): Collection
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $latestIds = DB::table('messages')
                ->selectRaw('DISTINCT ON (conversation_id) id')
                ->whereIn('conversation_id', $conversationIds)
                ->whereNull('deleted_at')
                ->orderBy('conversation_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id');

            if ($latestIds->isEmpty()) {
                return collect();
            }

            return Message::query()->whereIn('id', $latestIds)->get()->keyBy('conversation_id');
        }

        /** @var Collection<string, Message> $out */
        $out = collect();
        foreach ($conversationIds as $cid) {
            $m = Message::query()
                ->where('conversation_id', $cid)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
            if ($m !== null) {
                $out->put((string) $cid, $m);
            }
        }

        return $out;
    }

    /** Invalidate the unread count cache for a user. */
    public function invalidateUnreadCache(string $userId): void
    {
        Cache::forget("unread:{$userId}");
    }
}

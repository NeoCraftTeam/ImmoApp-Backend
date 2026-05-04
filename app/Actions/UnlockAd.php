<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\FirstAdUnlockCongratulationsMail;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Services\Chat\ConversationService;
use App\Services\PointService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Unlocks an ad for a user by deducting points and recording the interaction.
 *
 * Extracted from CreditController::unlock() for reuse across API,
 * Filament admin actions, and potential background processes.
 */
final readonly class UnlockAd
{
    public function __construct(
        private PointService $pointService,
        private ConversationService $conversationService,
    ) {}

    /**
     * @return array{status: string, points_balance?: int, current_balance?: int, required_points?: int}
     */
    public function execute(User $user, Ad $ad, int $cost): array
    {
        // Owner check is idempotent — safe outside transaction.
        if ($ad->user_id === $user->id) {
            return ['status' => 'owner'];
        }

        // SEC: All state checks MUST be inside the transaction with locks
        // to prevent TOCTOU double-deduction from concurrent requests.
        $result = DB::transaction(function () use ($user, $ad, $cost): array {
            // Lock the user row first to prevent concurrent balance reads.
            /** @var User $freshUser */
            $freshUser = User::query()->lockForUpdate()->findOrFail($user->id);

            // Check for existing unlock inside the transaction to prevent double-deduction.
            if (UnlockedAd::where('user_id', $user->id)->where('ad_id', $ad->id)->lockForUpdate()->exists()) {
                return ['status' => 'already_unlocked'];
            }

            if ($freshUser->point_balance < $cost) {
                return [
                    'status' => 'insufficient_points',
                    'current_balance' => (int) $freshUser->point_balance,
                    'required_points' => $cost,
                ];
            }

            $this->pointService->deduct(
                $user,
                $cost,
                "Déblocage annonce: {$ad->title}",
                $ad->id,
            );

            UnlockedAd::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'unlocked_at' => now(),
            ]);

            AdInteraction::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'type' => AdInteraction::TYPE_UNLOCK,
            ]);

            // Auto-create the conversation between tenant and landlord/agent.
            $landlordId = (string) ($ad->user_id ?? '');
            if ($landlordId !== '' && $landlordId !== $user->id) {
                try {
                    $this->conversationService->findOrCreate($ad->id, $user->id, $landlordId);
                } catch (\Throwable $e) {
                    Log::warning('[Chat] Could not auto-create conversation after unlock', [
                        'ad_id' => $ad->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'status' => 'unlocked',
                'points_balance' => (int) $freshUser->fresh()->point_balance,
            ];
        });

        if ($result['status'] === 'unlocked' && $user->isCustomer()) {
            $email = (string) $user->email;
            if ($email !== '' && !str_ends_with($email, '@clerk.local')
                && UnlockedAd::where('user_id', $user->id)->count() === 1) {
                try {
                    Mail::to($email, $user->firstname)->queue(
                        new FirstAdUnlockCongratulationsMail($user, $ad)
                    );
                } catch (\Throwable $e) {
                    Log::warning('First unlock congratulations email failed', [
                        'user_id' => $user->id,
                        'ad_id' => $ad->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }
}

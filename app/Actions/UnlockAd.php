<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Support\Facades\DB;

/**
 * Unlocks an ad for a user by deducting points and recording the interaction.
 *
 * Extracted from CreditController::unlock() for reuse across API,
 * Filament admin actions, and potential background processes.
 */
final readonly class UnlockAd
{
    public function __construct(private PointService $pointService) {}

    /**
     * @return array{status: string, points_balance?: int, current_balance?: int, required_points?: int}
     */
    public function execute(User $user, Ad $ad, int $cost): array
    {
        if ($ad->user_id === $user->id) {
            return ['status' => 'owner'];
        }

        if (UnlockedAd::where('user_id', $user->id)->where('ad_id', $ad->id)->exists()) {
            return ['status' => 'already_unlocked'];
        }

        if ($user->point_balance < $cost) {
            return [
                'status' => 'insufficient_points',
                'current_balance' => (int) $user->point_balance,
                'required_points' => $cost,
            ];
        }

        DB::transaction(function () use ($user, $ad, $cost): void {
            $this->pointService->deduct(
                $user,
                $cost,
                "Déblocage annonce: {$ad->title}",
                $ad->id,
            );

            UnlockedAd::firstOrCreate(
                ['user_id' => $user->id, 'ad_id' => $ad->id],
                ['unlocked_at' => now()],
            );

            AdInteraction::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'type' => AdInteraction::TYPE_UNLOCK,
            ]);
        });

        return [
            'status' => 'unlocked',
            'points_balance' => (int) $user->fresh()->point_balance,
        ];
    }
}

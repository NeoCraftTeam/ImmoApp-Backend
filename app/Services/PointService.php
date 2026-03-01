<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PointService
{
    /** Return the current cost in points to unlock an ad contact. */
    public function unlockCost(): int
    {
        return (int) Setting::get('unlock_cost_points', 2);
    }

    /** Return the welcome bonus points awarded to new users. */
    public function welcomeBonus(): int
    {
        return (int) Setting::get('welcome_bonus_points', 5);
    }

    /** Check whether the user has at least $cost points. */
    public function hasEnough(User $user, int $cost): bool
    {
        return $user->point_balance >= $cost;
    }

    /**
     * Debit $cost points from the user and record the transaction.
     *
     * @throws \RuntimeException when balance is insufficient
     */
    public function deduct(
        User $user,
        int $cost,
        string $description,
        ?string $adId = null
    ): PointTransaction {
        return DB::transaction(function () use ($user, $cost, $description, $adId): PointTransaction {
            /** @var \App\Models\User $freshUser */
            $freshUser = \App\Models\User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            if ($freshUser->point_balance < $cost) {
                throw new \RuntimeException('Solde de points insuffisant.');
            }

            $freshUser->decrement('point_balance', $cost);
            $user->point_balance = $freshUser->point_balance; // decrement() already updates in-memory

            return PointTransaction::create([
                'user_id' => $user->id,
                'type' => PointTransactionType::UNLOCK,
                'points' => -$cost,
                'description' => $description,
                'ad_id' => $adId,
            ]);
        });
    }

    /**
     * Credit $points to the user and record the transaction.
     */
    public function credit(
        User $user,
        int $points,
        PointTransactionType $type,
        string $description,
        ?string $paymentId = null
    ): PointTransaction {
        return DB::transaction(function () use ($user, $points, $type, $description, $paymentId): PointTransaction {
            $user->increment('point_balance', $points);

            return PointTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'points' => $points,
                'description' => $description,
                'payment_id' => $paymentId,
            ]);
        });
    }
}

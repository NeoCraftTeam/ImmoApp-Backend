<?php

declare(strict_types=1);

namespace App\Events\Credits;

use App\Models\PointTransaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusé sur le canal privé `user.{id}` à chaque changement de solde de
 * crédits (achat crédité, dépense pour déverrouillage/boost, remboursement).
 *
 * Permet au web et au mobile de mettre à jour le solde ET l'historique des
 * transactions EN TEMPS RÉEL, sans polling ni refetch. Diffusé à TOUTES les
 * sessions de l'utilisateur (multi-appareils) — le patch est idempotent
 * (solde = valeur absolue, transaction dédupliquée par uuid).
 *
 * `ShouldBroadcastNow` : le solde doit se refléter avec la même latence
 * perçue qu'un message chat. Le dispatch se fait APRÈS le commit DB.
 */
final class CreditsBalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly int $balance,
        public readonly PointTransaction $transaction,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'credits.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'balance' => $this->balance,
            'transaction' => [
                'uuid' => $this->transaction->getKey(),
                'type' => $this->transaction->type->value,
                'points' => (int) $this->transaction->points,
                'description' => $this->transaction->description,
                'created_at' => $this->transaction->created_at?->toIso8601String(),
            ],
        ];
    }
}

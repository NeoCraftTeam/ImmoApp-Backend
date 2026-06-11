<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\LeaseContract;

/**
 * Lifecycle status for a {@see LeaseContract}.
 *
 * - **Draft**: generated but not yet active (signature pending, no obligations).
 * - **Active**: in force, today is between `lease_start` and `lease_end`.
 * - **Expired**: `lease_end` has passed without renewal (flipped by the
 *   `leases:expire-overdue` scheduled command).
 * - **Terminated**: ended early by either party — `terminated_at` + reason captured.
 * - **Archived**: removed from active dashboards, kept for accounting (read-only).
 */
enum LeaseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Terminated => 'Résilié',
            self::Archived => 'Archivé',
        };
    }

    /** Lease is in force — counts towards occupancy and rent accrual. */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /** Cannot transition out of this state. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Terminated, self::Archived => true,
            default => false,
        };
    }
}

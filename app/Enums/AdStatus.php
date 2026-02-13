<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdStatus: string implements HasLabel
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case RENT = 'rent';
    case PENDING = 'pending';
    case SOLD = 'sold';

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Disponible',
            self::RESERVED => 'Réservé',
            self::RENT => 'En location',
            self::PENDING => 'En attente',
            self::SOLD => 'Vendu',
        };
    }

    /**
     * Valid transitions from this status.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::AVAILABLE],
            self::AVAILABLE => [self::RESERVED, self::RENT, self::SOLD],
            self::RESERVED => [self::AVAILABLE, self::RENT, self::SOLD],
            self::RENT => [self::AVAILABLE],
            self::SOLD => [self::AVAILABLE],
        };
    }

    /**
     * Check if a transition to the given status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}

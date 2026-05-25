<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DisputeStatus: string implements HasLabel
{
    case OPEN = 'open';
    case UNDER_REVIEW = 'under_review';
    case MEDIATION = 'mediation';
    case RESOLVED_INITIATOR = 'resolved_initiator';
    case RESOLVED_RESPONDENT = 'resolved_respondent';
    case RESOLVED_AMICABLY = 'resolved_amicably';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Ouvert',
            self::UNDER_REVIEW => 'En examen',
            self::MEDIATION => 'En médiation',
            self::RESOLVED_INITIATOR => 'Résolu (initiateur)',
            self::RESOLVED_RESPONDENT => 'Résolu (défendeur)',
            self::RESOLVED_AMICABLY => 'Résolu à l\'amiable',
            self::REJECTED => 'Rejeté',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::OPEN, self::UNDER_REVIEW, self::MEDIATION], true);
    }

    public function isResolved(): bool
    {
        return in_array($this, [
            self::RESOLVED_INITIATOR,
            self::RESOLVED_RESPONDENT,
            self::RESOLVED_AMICABLY,
            self::REJECTED,
        ], true);
    }

    /**
     * Allowed transitions from this state.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::OPEN => [self::UNDER_REVIEW, self::REJECTED],
            self::UNDER_REVIEW => [self::MEDIATION, self::REJECTED],
            self::MEDIATION => [
                self::RESOLVED_INITIATOR,
                self::RESOLVED_RESPONDENT,
                self::RESOLVED_AMICABLY,
            ],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }
}

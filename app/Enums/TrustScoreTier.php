<?php

declare(strict_types=1);

namespace App\Enums;

enum TrustScoreTier: string
{
    case NonVerifie = 'non_verifie';
    case Bronze = 'bronze';
    case Argent = 'argent';
    case Or = 'or';
    case Platine = 'platine';

    public function label(): string
    {
        return match ($this) {
            self::NonVerifie => 'Non vérifié',
            self::Bronze => 'Bronze',
            self::Argent => 'Argent',
            self::Or => 'Or',
            self::Platine => 'Platine',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NonVerifie => 'gray',
            self::Bronze => 'warning',
            self::Argent => 'info',
            self::Or => 'success',
            self::Platine => 'primary',
        };
    }

    public function hexColor(): string
    {
        return match ($this) {
            self::NonVerifie => '#9CA3AF',
            self::Bronze => '#D97706',
            self::Argent => '#64748B',
            self::Or => '#EAB308',
            self::Platine => '#0D9488',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 80 => self::Platine,
            $score >= 60 => self::Or,
            $score >= 40 => self::Argent,
            $score >= 20 => self::Bronze,
            default => self::NonVerifie,
        };
    }

    public function minScore(): int
    {
        return match ($this) {
            self::NonVerifie => 0,
            self::Bronze => 20,
            self::Argent => 40,
            self::Or => 60,
            self::Platine => 80,
        };
    }
}

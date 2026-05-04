<?php

declare(strict_types=1);

use App\Support\Money;

it('formats XAF amounts with FCFA label and never ISO XAF', function (): void {
    $s = Money::format(15_000, 'XAF');
    expect($s)->toContain('FCFA')->not->toContain('XAF');
});

it('formats XOF amounts with FCFA label and never ISO XOF', function (): void {
    $s = Money::format(15_000, 'XOF');
    expect($s)->toContain('FCFA')->not->toContain('XOF');
});

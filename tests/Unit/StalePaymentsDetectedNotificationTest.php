<?php

declare(strict_types=1);

use App\Notifications\StalePaymentsDetectedNotification;

it('accords the mail message in the singular for a single payment', function (): void {
    $mail = new StalePaymentsDetectedNotification(count: 1, hours: 6)->toMail(new stdClass);

    expect($mail->subject)->toBe('1 paiement bloqué détecté')
        ->and($mail->introLines)->toContain(
            '1 paiement en statut PENDING depuis plus de 6 h a été marqué comme échoué.'
        );
});

it('accords the mail message in the plural for several payments', function (): void {
    $mail = new StalePaymentsDetectedNotification(count: 3, hours: 24)->toMail(new stdClass);

    expect($mail->subject)->toBe('3 paiements bloqués détectés')
        ->and($mail->introLines)->toContain(
            '3 paiements en statut PENDING depuis plus de 24 h ont été marqués comme échoués.'
        );
});

it('never emits the "paiement s" or double-space pluralisation glitch', function (): void {
    foreach ([1, 2, 10] as $count) {
        $line = new StalePaymentsDetectedNotification($count, 6)->toMail(new stdClass)->introLines[0];

        expect($line)->not->toContain('paiement s')
            ->and($line)->not->toContain('  ');
    }
});

it('accords the database payload and carries structured data', function (): void {
    expect(new StalePaymentsDetectedNotification(1, 6)->toArray(new stdClass))->toBe([
        'type' => 'stale_payments',
        'count' => 1,
        'hours' => 6,
        'message' => '1 paiement bloqué marqué comme échoué après 6 h.',
    ]);

    expect(new StalePaymentsDetectedNotification(5, 12)->toArray(new stdClass))->toBe([
        'type' => 'stale_payments',
        'count' => 5,
        'hours' => 12,
        'message' => '5 paiements bloqués marqués comme échoués après 12 h.',
    ]);
});

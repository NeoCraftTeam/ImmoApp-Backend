<?php

declare(strict_types=1);

use App\Enums\SuccessCode;
use App\Support\ApiResponse;

it('resolves the localized message for each success code', function (SuccessCode $code, string $expected): void {
    expect($code->message())->toBe($expected);
})->with([
    'logout' => [SuccessCode::Logout, 'Déconnexion réussie.'],
    'viewing confirmed' => [SuccessCode::ViewingConfirmed, 'Visite confirmée. Le locataire a été notifié.'],
    'availability created' => [SuccessCode::AvailabilityScheduleCreated, 'Planning de disponibilité créé avec succès.'],
    'availability updated' => [SuccessCode::AvailabilityScheduleUpdated, 'Planning de disponibilité mis à jour.'],
]);

it('keeps success copy in French even when the request locale is not French', function (): void {
    app()->setLocale('en');

    expect(SuccessCode::Logout->message())->toBe('Déconnexion réussie.');
});

it('builds a success envelope with message and machine code from a success code', function (): void {
    $response = ApiResponse::successCode(SuccessCode::ViewingConfirmed, ['id' => 42]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe([
            'success' => true,
            'message' => 'Visite confirmée. Le locataire a été notifié.',
            'code' => 'VIEWING_CONFIRMED',
            'data' => ['id' => 42],
        ]);
});

it('honors a custom status code and omits data when none is given', function (): void {
    $response = ApiResponse::successCode(SuccessCode::AvailabilityScheduleCreated, null, 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true))->toBe([
            'success' => true,
            'message' => 'Planning de disponibilité créé avec succès.',
            'code' => 'AVAILABILITY_SCHEDULE_CREATED',
        ]);
});

it('omits the code field on plain success responses', function (): void {
    $response = ApiResponse::success('Opération réussie.');

    expect($response->getData(true))->toBe([
        'success' => true,
        'message' => 'Opération réussie.',
    ]);
});

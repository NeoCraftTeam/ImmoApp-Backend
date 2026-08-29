<?php

declare(strict_types=1);

use App\Exceptions\Viewing\ClientHasActiveReservationForAdException;
use App\Exceptions\Viewing\ScheduleHasActiveReservationsException;
use App\Exceptions\Viewing\SelfReservationException;
use App\Exceptions\Viewing\SlotAlreadyReservedException;
use App\Exceptions\Viewing\SlotNotAvailableException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

it('renders a flat unified envelope without the legacy {error:{}} key', function (object $exception, int $status, string $code): void {
    $response = $exception->render();
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe($status)
        ->and($data)->not->toHaveKey('error')
        ->and($data['code'])->toBe($code)
        ->and($data['message'])->toBeString()->and($data['message'])->not->toBe('');
})->with([
    'slot not available' => [new SlotNotAvailableException, Response::HTTP_GONE, 'SLOT_NOT_AVAILABLE'],
    'slot already reserved' => [new SlotAlreadyReservedException, Response::HTTP_CONFLICT, 'SLOT_ALREADY_RESERVED'],
    'client active reservation' => [new ClientHasActiveReservationForAdException, Response::HTTP_CONFLICT, 'CLIENT_ACTIVE_RESERVATION_EXISTS'],
    'self reservation' => [new SelfReservationException, Response::HTTP_FORBIDDEN, 'SELF_RESERVATION_NOT_ALLOWED'],
    'schedule has active reservations' => [new ScheduleHasActiveReservationsException(new Collection), Response::HTTP_CONFLICT, 'SCHEDULE_HAS_ACTIVE_RESERVATIONS'],
]);

it('keeps a safe top-level hint on the slot exception', function (): void {
    $data = (new SlotNotAvailableException)->render()->getData(true);

    expect($data['hint'])->toBe('Ce créneau n\'existe pas ou la date est passée.');
});

it('surfaces the reservations payload at the top level for schedule conflicts', function (): void {
    $data = new ScheduleHasActiveReservationsException(new Collection)->render()->getData(true);

    expect($data)->toHaveKey('reservations')
        ->and($data['reservations'])->toBe([]);
});

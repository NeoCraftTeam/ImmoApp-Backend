<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Viewing\StoreAvailabilityRequest;
use App\Http\Requests\Viewing\UpdateAvailabilityRequest;
use App\Http\Resources\AvailabilitySlotResource;
use App\Http\Resources\TentativeReservationResource;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\Zap\Schedule;
use App\Policies\ViewingAvailabilityPolicy;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="📅 Disponibilités", description="Gestion des disponibilités de visite (propriétaires)")
 */
final readonly class ViewingAvailabilityController
{
    public function __construct(
        private ViewingScheduleServiceInterface $scheduleService,
        private ReservationServiceInterface $reservationService,
    ) {}

    /**
     * List all availability schedules for a property.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/availability",
     *     summary="Lister les plannings de disponibilité",
     *     tags={"📅 Disponibilités"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Liste des plannings"),
     *     @OA\Response(response=403, description="Non autorisé")
     * )
     */
    public function index(Request $request, Ad $ad): AnonymousResourceCollection
    {
        $this->authorize($request, $ad);

        $schedules = $ad->availabilitySchedules()
            ->with('periods')
            ->latest()
            ->get();

        return AvailabilitySlotResource::collection($schedules);
    }

    /**
     * Create a new availability schedule.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/availability",
     *     summary="Créer un planning de disponibilité",
     *     tags={"📅 Disponibilités"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=201, description="Planning créé"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreAvailabilityRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize($request, $ad);

        $schedule = $this->scheduleService->createAvailability($ad, $request->validated());

        Cache::tags(['slots', "ad:{$ad->id}"])->flush();

        return response()->json([
            'data' => new AvailabilitySlotResource($schedule->load('periods')),
            'message' => 'Planning de disponibilité créé avec succès.',
        ], 201);
    }

    /**
     * Update an existing availability schedule.
     *
     * @OA\Put(
     *     path="/api/v1/ads/{ad}/availability/{schedule}",
     *     summary="Modifier un planning de disponibilité",
     *     tags={"📅 Disponibilités"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="schedule", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Planning mis à jour"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=409, description="Réservations actives existent")
     * )
     */
    public function update(UpdateAvailabilityRequest $request, Ad $ad, Schedule $schedule): JsonResponse
    {
        $this->authorize($request, $ad);

        // Guard: if periods or recurrence change, assert no active reservations.
        $sensitiveParts = array_intersect_key($request->validated(), array_flip(['periods', 'recurrence', 'recurrence_days', 'starts_on']));
        if (!empty($sensitiveParts)) {
            $this->reservationService->assertNoActiveReservationsForSchedule($schedule);
        }

        $updated = $this->scheduleService->updateAvailability($ad, $schedule, $request->validated());

        Cache::tags(['slots', "ad:{$ad->id}"])->flush();

        return response()->json([
            'data' => new AvailabilitySlotResource($updated->load('periods')),
            'message' => 'Planning de disponibilité mis à jour.',
        ]);
    }

    /**
     * Delete an availability schedule.
     *
     * @OA\Delete(
     *     path="/api/v1/ads/{ad}/availability/{schedule}",
     *     summary="Supprimer un planning de disponibilité",
     *     tags={"📅 Disponibilités"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="schedule", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="Supprimé"),
     *     @OA\Response(response=403, description="Non autorisé")
     * )
     */
    public function destroy(Request $request, Ad $ad, Schedule $schedule): JsonResponse
    {
        $this->authorize($request, $ad);

        // Cancel all pending reservations for this schedule.
        TentativeReservation::query()
            ->where('appointment_schedule_id', $schedule->id)
            ->active()
            ->each(fn (TentativeReservation $r) => $this->reservationService->cancel($r, $request->user(), 'Planning supprimé par le propriétaire.'));

        $schedule->delete();

        Cache::tags(['slots', "ad:{$ad->id}"])->flush();

        return response()->json(null, 204);
    }

    /**
     * Get the slot-status calendar view for a date range (landlord dashboard).
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/availability/calendar",
     *     summary="Calendrier des créneaux avec statuts",
     *     tags={"📅 Disponibilités"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Calendrier des disponibilités")
     * )
     */
    public function calendar(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize($request, $ad);

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->addDays(30)->toDateString());

        $slots = $this->scheduleService->getBookableSlotsForRange($ad, $from, $to);

        // Overlay reservation statuses.
        $activeReservations = TentativeReservation::query()
            ->where('ad_id', $ad->id)
            ->active()
            ->whereDate('slot_date', '>=', $from)
            ->whereDate('slot_date', '<=', $to)
            ->get()
            ->groupBy(fn (TentativeReservation $r): string => $r->slot_date->toDateString());

        $calendar = [];
        foreach ($slots as $date => $daySlots) {
            $calendar[$date] = [
                'slots' => collect($daySlots)->map(function (array $slot) use ($date, $activeReservations): array {
                    /** @var TentativeReservation|null $reservation */
                    $reservation = $activeReservations->get($date)?->first(
                        fn (TentativeReservation $r): bool => \Carbon\Carbon::parse($r->slot_starts_at)->format('H:i') === $slot['starts_at']
                    );

                    $entry = [
                        'starts_at' => $slot['starts_at'],
                        'ends_at' => $slot['ends_at'],
                        'status' => $reservation ? 'tentatively_reserved' : 'available',
                    ];

                    if ($reservation) {
                        $entry['reservation'] = [
                            'id' => $reservation->id,
                            'client_name' => $reservation->client->firstname.' '.$reservation->client->lastname,
                            'client_message' => $reservation->client_message,
                            'reserved_at' => $reservation->created_at->toIso8601String(),
                            'expires_at' => $reservation->expires_at->toIso8601String(),
                            'status' => $reservation->status->value,
                        ];
                    }

                    return $entry;
                })->toArray(),
            ];
        }

        return response()->json(['data' => $calendar]);
    }

    /**
     * List reservations for a property (landlord view).
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/reservations",
     *     summary="Lister les réservations provisoires d'une annonce",
     *     tags={"📅 Disponibilités"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Liste paginée des réservations")
     * )
     */
    public function reservations(Request $request, Ad $ad): AnonymousResourceCollection
    {
        $this->authorize($request, $ad);

        $paginator = $this->reservationService->listForAd($ad, $request->only(['status', 'from', 'to']));

        return TentativeReservationResource::collection($paginator);
    }

    // -------------------------------------------------------------------------

    private function authorize(Request $request, Ad $ad): void
    {
        abort_unless(
            app(ViewingAvailabilityPolicy::class)->manage($request->user(), $ad),
            403,
            'Vous n\'êtes pas autorisé à gérer la disponibilité de cette annonce.'
        );
    }
}

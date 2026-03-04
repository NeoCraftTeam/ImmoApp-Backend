<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Viewing\CancelReservationRequest;
use App\Http\Requests\Viewing\StoreTentativeReservationRequest;
use App\Http\Resources\TentativeReservationResource;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Policies\TentativeReservationPolicy;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🗓️ Réservations", description="Réservations provisoires de créneaux de visite (clients)")
 */
final readonly class ViewingReservationController
{
    public function __construct(
        private ViewingScheduleServiceInterface $scheduleService,
        private ReservationServiceInterface $reservationService,
    ) {}

    /**
     * Get all available time slots for a property (public).
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/slots",
     *     summary="Créneaux disponibles pour une annonce",
     *     description="Retourne tous les créneaux de visite disponibles pour une annonce sur une plage de dates.",
     *     tags={"🗓️ Réservations"},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="from", in="query", description="Date de début (défaut: aujourd'hui)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", description="Date de fin (défaut: +14 jours)", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Créneaux disponibles par date")
     * )
     */
    public function slots(Request $request, Ad $ad): JsonResponse
    {
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->addDays(14)->toDateString());

        $cacheKey = "slots:{$ad->id}:{$from}:{$to}";

        $data = Cache::tags(['slots', "ad:{$ad->id}"])->remember($cacheKey, 60, function () use ($ad, $from, $to): array {
            $slotsRaw = $this->scheduleService->getBookableSlotsForRange($ad, $from, $to);

            // Fetch active reservations in range to overlay status.
            $activeReservations = \App\Models\TentativeReservation::query()
                ->where('ad_id', $ad->id)
                ->active()
                ->whereDate('slot_date', '>=', $from)
                ->whereDate('slot_date', '<=', $to)
                ->get()
                ->groupBy(fn (\App\Models\TentativeReservation $r): string => $r->slot_date->toDateString());

            $slotsByDate = [];
            foreach ($slotsRaw as $date => $daySlots) {
                $slotsByDate[$date] = collect($daySlots)->map(function (array $slot) use ($date, $activeReservations): array {
                    $isReserved = $activeReservations->get($date)?->contains(
                        fn (\App\Models\TentativeReservation $r): bool => \Carbon\Carbon::parse($r->slot_starts_at)->format('H:i') === $slot['starts_at']
                    ) ?? false;

                    return [
                        'starts_at' => $slot['starts_at'],
                        'ends_at' => $slot['ends_at'],
                        'is_available' => !$isReserved,
                    ];
                })->values()->toArray();
            }

            return $slotsByDate;
        });

        return response()->json([
            'data' => [
                'ad_id' => $ad->id,
                'slot_duration_minutes' => $this->scheduleService->getSlotDuration($ad),
                'slots_by_date' => $data,
            ],
        ]);
    }

    /**
     * Tentatively reserve a slot (authenticated client).
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/reservations",
     *     summary="Réserver provisoirement un créneau de visite",
     *     description="Crée une réservation provisoire pour un créneau de visite. Valide 24h.",
     *     tags={"🗓️ Réservations"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"slot_date","slot_starts_at","slot_ends_at"},
     *
     *             @OA\Property(property="slot_date", type="string", format="date"),
     *             @OA\Property(property="slot_starts_at", type="string", example="10:00"),
     *             @OA\Property(property="slot_ends_at", type="string", example="10:30"),
     *             @OA\Property(property="client_message", type="string", maxLength=500)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Réservation provisoire créée"),
     *     @OA\Response(response=403, description="Auto-réservation non autorisée"),
     *     @OA\Response(response=409, description="Créneau déjà réservé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreTentativeReservationRequest $request, Ad $ad): JsonResponse
    {
        $reservation = $this->reservationService->reserve($ad, $request->user(), $request->validated());

        Cache::tags(['slots', "ad:{$ad->id}"])->flush();

        return response()->json([
            'data' => new TentativeReservationResource($reservation->load('ad')),
            'message' => 'Votre réservation provisoire a bien été enregistrée.',
        ], 201);
    }

    /**
     * List the authenticated client's reservations.
     *
     * @OA\Get(
     *     path="/api/v1/my/reservations",
     *     summary="Mes réservations provisoires",
     *     tags={"🗓️ Réservations"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","confirmed","cancelled","expired"})),
     *
     *     @OA\Response(response=200, description="Liste paginée de mes réservations")
     * )
     */
    public function myReservations(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->reservationService->listForClient($request->user(), $request->only(['status']));

        return TentativeReservationResource::collection($paginator);
    }

    /**
     * Cancel a tentative reservation.
     *
     * @OA\Delete(
     *     path="/api/v1/reservations/{reservation}",
     *     summary="Annuler une réservation provisoire",
     *     tags={"🗓️ Réservations"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="reservation", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Réservation annulée"),
     *     @OA\Response(response=403, description="Non autorisé")
     * )
     */
    public function cancel(CancelReservationRequest $request, TentativeReservation $reservation): JsonResponse
    {
        abort_unless(
            app(TentativeReservationPolicy::class)->cancel($request->user(), $reservation),
            403,
            'Vous n\'êtes pas autorisé à annuler cette réservation.'
        );

        $cancelled = $this->reservationService->cancel(
            $reservation,
            $request->user(),
            $request->input('cancellation_reason')
        );

        Cache::tags(['slots', "ad:{$cancelled->ad_id}"])->flush();

        return response()->json([
            'data' => new TentativeReservationResource($cancelled->load('ad')),
            'message' => 'Réservation provisoire annulée.',
        ]);
    }
}

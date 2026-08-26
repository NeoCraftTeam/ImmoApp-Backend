<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lease;

use App\Actions\Reservation\ConfirmReservationAction;
use App\Contracts\ReservationServiceInterface;
use App\Contracts\ViewingScheduleServiceInterface;
use App\Enums\ReservationStatus;
use App\Enums\SuccessCode;
use App\Http\Requests\Viewing\CancelReservationRequest;
use App\Http\Requests\Viewing\StoreTentativeReservationRequest;
use App\Http\Resources\TentativeReservationResource;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Policies\TentativeReservationPolicy;
use App\Support\ApiResponse;
use App\Support\ViewingSlotsResponseCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🗓️ Réservations", description="Réservations provisoires de créneaux de visite (clients)")
 */
final readonly class ViewingReservationController
{
    public function __construct(
        private ViewingScheduleServiceInterface $scheduleService,
        private ReservationServiceInterface $reservationService,
        private ConfirmReservationAction $confirmReservation,
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
        // Accept a single ?date=YYYY-MM-DD alias in addition to ?from/to.
        $singleDate = $request->input('date');
        $from = $singleDate ?? $request->input('from', now()->toDateString());
        $to = $singleDate ?? $request->input('to', now()->addDays(14)->toDateString());

        $data = ViewingSlotsResponseCache::remember($ad, $from, $to, function () use ($ad, $from, $to): array {
            $slotsRaw = $this->scheduleService->getBookableSlotsForRange($ad, $from, $to);

            // Fetch active reservations in range to overlay status.
            $activeReservations = TentativeReservation::query()
                ->where('ad_id', $ad->id)
                ->active()
                ->whereDate('slot_date', '>=', $from)
                ->whereDate('slot_date', '<=', $to)
                ->get()
                ->groupBy(fn (TentativeReservation $r): string => $r->slot_date->toDateString());

            $slotsByDate = [];
            foreach ($slotsRaw as $date => $daySlots) {
                $slotsByDate[$date] = collect($daySlots)->map(function (array $slot) use ($date, $activeReservations): array {
                    $isReserved = $activeReservations->get($date)?->contains(
                        fn (TentativeReservation $r): bool => Carbon::parse($r->slot_starts_at)->format('H:i') === $slot['start_time']
                    ) ?? false;

                    return [
                        'starts_at' => $slot['start_time'],
                        'ends_at' => $slot['end_time'],
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
     *     @OA\Response(response=403, description="Annonce non débloquée ou auto-réservation non autorisée"),
     *     @OA\Response(response=409, description="Créneau déjà réservé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(StoreTentativeReservationRequest $request, Ad $ad): JsonResponse
    {
        abort_unless(
            $ad->isUnlockedFor($request->user()),
            403,
            'Vous devez débloquer cette annonce avant de pouvoir réserver une visite.'
        );

        $idempotencyKey = $request->header('X-Idempotency-Key');
        if ($idempotencyKey) {
            $cacheKey = "reservation_idempotency:{$request->user()->id}:{$idempotencyKey}";
            $existing = cache()->get($cacheKey);
            if ($existing) {
                $cached = TentativeReservation::find($existing);
                if ($cached) {
                    return response()->json([
                        'data' => new TentativeReservationResource($cached->load('ad')),
                        'message' => 'Votre réservation provisoire a bien été enregistrée.',
                    ], 200);
                }
            }
        }

        $reservation = $this->reservationService->reserve($ad, $request->user(), $request->validated());

        if ($idempotencyKey) {
            cache()->put("reservation_idempotency:{$request->user()->id}:{$idempotencyKey}", $reservation->id, now()->addHours(24));
        }

        ViewingSlotsResponseCache::bumpGeneration($ad);

        return response()->json([
            'data' => new TentativeReservationResource($reservation->load('ad')),
            'message' => 'Votre réservation provisoire a bien été enregistrée.',
        ], 201);
    }

    /**
     * List all viewing reservations for the landlord's ads.
     */
    public function myReservationsAsLandlord(Request $request): AnonymousResourceCollection
    {
        // Join ad on user_id (indexed) instead of whereHas subquery for a faster
        // execution plan, then select only tentative_reservations.* to avoid the
        // implicit ambiguity. Eager-load relations with explicit column lists.
        $paginator = TentativeReservation::query()
            ->select('tentative_reservations.*')
            ->join('ad', 'ad.id', '=', 'tentative_reservations.ad_id')
            ->where('ad.user_id', $request->user()->id)
            ->with([
                'ad' => fn ($q) => $q->with([
                    'quarter',
                    'media',
                    'ad_type',
                    'user',
                ]),
                'client:id,firstname,lastname,avatar,phone_number,email',
            ])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('tentative_reservations.status', $request->input('status'))
            )
            ->orderByDesc('tentative_reservations.slot_date')
            ->orderBy('tentative_reservations.slot_starts_at')
            ->paginate(max(1, min(50, (int) $request->input('per_page', 15))));

        return TentativeReservationResource::collection($paginator);
    }

    /**
     * Confirm a pending reservation (landlord only).
     */
    public function confirm(Request $request, TentativeReservation $reservation): JsonResponse
    {
        abort_unless(
            $reservation->isOwnedByLandlord($request->user()),
            403,
            'Seul le propriétaire peut confirmer cette réservation.'
        );

        abort_unless(
            in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Expired], true),
            422,
            'Cette réservation a déjà été confirmée ou annulée.'
        );

        $confirmed = $this->confirmReservation->execute($reservation);

        return ApiResponse::successCode(
            SuccessCode::ViewingConfirmed,
            new TentativeReservationResource($confirmed->load('ad'))->resolve($request),
        );
    }

    /**
     * Update landlord notes on a reservation.
     */
    public function updateNotes(Request $request, TentativeReservation $reservation): JsonResponse
    {
        abort_unless(
            $reservation->isOwnedByLandlord($request->user()),
            403,
            'Seul le propriétaire peut modifier les notes.'
        );

        $request->validate([
            'landlord_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reservation->update(['landlord_notes' => $request->input('landlord_notes')]);

        return response()->json([
            'data' => new TentativeReservationResource($reservation->load('ad')),
            'message' => 'Notes enregistrées.',
        ]);
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
        $paginator = $this->reservationService->listForClient($request->user(), $request->only(['status', 'ad_id']));

        return TentativeReservationResource::collection($paginator);
    }

    /**
     * Mark a confirmed reservation as no-show (landlord only).
     */
    public function noShow(Request $request, TentativeReservation $reservation): JsonResponse
    {
        abort_unless(
            $reservation->isOwnedByLandlord($request->user()),
            403,
            'Seul le propriétaire peut marquer une réservation comme absence.'
        );

        abort_unless(
            $reservation->status === ReservationStatus::Confirmed,
            422,
            'Seules les réservations confirmées peuvent être marquées comme absences.'
        );

        $updated = $this->reservationService->markNoShow($reservation);

        return response()->json([
            'data' => new TentativeReservationResource($updated->load('ad')),
            'message' => 'Réservation marquée comme absence.',
        ]);
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

        ViewingSlotsResponseCache::bumpGenerationForAdId($reservation->ad_id);

        return response()->json([
            'data' => new TentativeReservationResource($cancelled->load('ad')),
            'message' => 'Réservation provisoire annulée.',
        ]);
    }
}

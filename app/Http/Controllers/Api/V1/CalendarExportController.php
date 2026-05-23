<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Spatie\IcalendarGenerator\Components\Alert;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;

/**
 * Generates personal .ics calendar feeds for viewing reservations.
 *
 * Client feed  : GET /users/{user}/calendar.ics      — reservations where user is the visitor
 * Landlord feed: GET /users/{user}/landlord-calendar.ics — confirmed/pending visits on user's ads
 *
 * Both URLs are signed (1-year TTL) so no auth middleware is required —
 * users can subscribe directly in Google/Apple/Outlook.
 */
final class CalendarExportController
{
    private const TIMEZONE = 'Africa/Douala';

    // ── Client feed ────────────────────────────────────────────────────────────

    public function ics(Request $request, User $user): Response
    {
        abort_unless($request->hasValidSignature(), 403, 'Lien de calendrier invalide ou expiré.');

        $reservations = TentativeReservation::query()
            ->where('client_id', $user->id)
            ->whereNotIn('status', [ReservationStatus::Expired, ReservationStatus::NoShow, ReservationStatus::Cancelled])
            ->with(['ad.quarter.city', 'ad.user'])
            ->orderBy('slot_date')
            ->orderBy('slot_starts_at')
            ->get();

        $calendar = Calendar::create('Mes visites KeyHome')
            ->productIdentifier('-//KeyHome//Visites Client//FR')
            ->refreshInterval(60);

        foreach ($reservations as $r) {
            $calendar->event($this->buildEvent($r, isLandlord: false));
        }

        return $this->calendarResponse($calendar, 'keyhome-visites.ics');
    }

    /**
     * Generate (or regenerate) the signed .ics URL for the authenticated client.
     */
    public function calendarUrl(Request $request): JsonResponse
    {
        $user = $request->user();

        $url = URL::temporarySignedRoute(
            'calendar.ics',
            now()->addYear(),
            ['user' => $user->id],
        );

        return response()->json(['url' => $url]);
    }

    // ── Landlord feed ──────────────────────────────────────────────────────────

    /**
     * .ics feed of confirmed/pending visits on the landlord's ads.
     */
    public function landlordIcs(Request $request, User $user): Response
    {
        abort_unless($request->hasValidSignature(), 403, 'Lien de calendrier invalide ou expiré.');

        $reservations = TentativeReservation::query()
            ->select('tentative_reservations.*')
            ->join('ad', 'ad.id', '=', 'tentative_reservations.ad_id')
            ->where('ad.user_id', $user->id)
            ->whereIn('tentative_reservations.status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->with(['ad.quarter.city', 'client'])
            ->orderBy('tentative_reservations.slot_date')
            ->orderBy('tentative_reservations.slot_starts_at')
            ->get();

        $calendar = Calendar::create('Mes visites bailleur — KeyHome')
            ->productIdentifier('-//KeyHome//Visites Bailleur//FR')
            ->refreshInterval(60);

        foreach ($reservations as $r) {
            $calendar->event($this->buildEvent($r, isLandlord: true));
        }

        return $this->calendarResponse($calendar, 'keyhome-visites-bailleur.ics');
    }

    /**
     * Generate (or regenerate) the signed landlord .ics URL.
     */
    public function landlordCalendarUrl(Request $request): JsonResponse
    {
        $user = $request->user();

        $url = URL::temporarySignedRoute(
            'calendar.landlord-ics',
            now()->addYear(),
            ['user' => $user->id],
        );

        return response()->json(['url' => $url]);
    }

    // ── Shared helpers ─────────────────────────────────────────────────────────

    /**
     * Build an iCalendar VEVENT with a 30-minute VALARM reminder.
     */
    private function buildEvent(TentativeReservation $r, bool $isLandlord): Event
    {
        $start = Carbon::parse(
            $r->slot_date->toDateString().' '.$r->slot_starts_at,
            self::TIMEZONE,
        );
        $end = Carbon::parse(
            $r->slot_date->toDateString().' '.$r->slot_ends_at,
            self::TIMEZONE,
        );

        $location = implode(', ', array_filter([
            $r->ad->quarter?->name,
            $r->ad->quarter?->city?->name,
            'Cameroun',
        ]));

        $icsStatus = match ($r->status) {
            ReservationStatus::Confirmed => EventStatus::Confirmed,
            ReservationStatus::Cancelled => EventStatus::Cancelled,
            default => EventStatus::Tentative,
        };

        if ($isLandlord) {
            $title = "Visite — {$r->ad->title}";
            $description = "Visiteur : {$r->client->firstname} {$r->client->lastname}\nTél : ".($r->client->phone_number ?? 'non renseigné')."\nStatut : ".$r->status->label()."\nRéférence : {$r->id}\nkeyhome.app/owner/viewings";
        } else {
            $title = "Visite — {$r->ad->title}";
            $description = 'Statut : '.$r->status->label()."\nRéférence : {$r->id}\nkeyhome.app/my/reservations";
        }

        $event = Event::create($title)
            ->uniqueIdentifier("reservation-{$r->id}-".($isLandlord ? 'landlord' : 'client').'@keyhome.app')
            ->startsAt($start)
            ->endsAt($end)
            ->address($location)
            ->description($description)
            ->status($icsStatus)
            ->sequence((int) $r->updated_at?->timestamp)
            ->alert(Alert::minutesBeforeStart(30, "Rappel : {$title}"));

        return $event;
    }

    private function calendarResponse(Calendar $calendar, string $filename): Response
    {
        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}

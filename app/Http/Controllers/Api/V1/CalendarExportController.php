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
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;

/**
 * Generates a personal .ics calendar feed of the user's confirmed/pending viewing reservations.
 * The URL is signed (temporary, 1-year TTL) so no auth middleware is required —
 * users can subscribe directly in Google/Apple/Outlook with the URL.
 */
final class CalendarExportController
{
    private const TIMEZONE = 'Africa/Douala';

    public function ics(Request $request, User $user): Response
    {
        abort_unless($request->hasValidSignature(), 403, 'Lien de calendrier invalide ou expiré.');

        $reservations = TentativeReservation::query()
            ->where('client_id', $user->id)
            ->whereNotIn('status', [ReservationStatus::Expired, ReservationStatus::NoShow])
            ->with(['ad.quarter.city'])
            ->orderBy('slot_date')
            ->orderBy('slot_starts_at')
            ->get();

        $calendar = Calendar::create('Mes visites KeyHome')
            ->productIdentifier('-//KeyHome//Visites//FR')
            ->refreshInterval(60);

        foreach ($reservations as $r) {
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

            $event = Event::create("Visite — {$r->ad->title}")
                ->uniqueIdentifier("reservation-{$r->id}@keyhome.app")
                ->startsAt($start)
                ->endsAt($end)
                ->address($location)
                ->description('Statut : '.$r->status->label()."\nRéférence : {$r->id}\nkeyhome.app")
                ->status($icsStatus)
                ->sequence((int) $r->updated_at?->timestamp);

            $calendar->event($event);
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="keyhome-visites.ics"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Generate (or regenerate) the signed .ics URL for the authenticated user.
     * Returns the URL as JSON — frontend copies it into the "Subscribe" input.
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
}

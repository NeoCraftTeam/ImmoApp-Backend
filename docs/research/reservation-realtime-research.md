# Research — Système de Réservation Complet + Calendar Sync + Real-Time Event-Driven

> Recherche effectuée le 23/05/2026. Sources : Firecrawl web crawl, Packagist, GitHub, Medium, System Design newsletters.

---

## Table des matières

1. [État de l'art — système de réservation (Booking.com / Airbnb)](#1-état-de-lart)
2. [Calendar Sync — iCal, Google, Apple, Outlook](#2-calendar-sync)
3. [Real-Time Event-Driven — Laravel Reverb + Next.js](#3-real-time-event-driven)
4. [Gaps identifiés dans KeyHome](#4-gaps-keyhome)
5. [Recommandations et plan d'implémentation](#5-recommandations)

---

## 1. État de l'art

### Architecture état machine (Booking.com / Airbnb pattern)

Les plateformes OTA de référence utilisent toutes un **state machine strict** côté backend :

```
PENDING (TTL 72h) → CONFIRMED → COMPLETED
       ↓                ↓
   EXPIRED          CANCELLED (by client | landlord | system)
```

**Patterns clés identifiés :**

- **Double-booking prevention** : row-level lock `SELECT FOR UPDATE` dans une transaction DB avant toute création. ✅ Déjà implémenté dans `ReservationService::reserve()`.
- **Idempotency key** : chaque tentative de réservation doit avoir une clé unique côté client pour éviter les doubles soumissions sur réseau instable.
- **Optimistic locking** : version field sur la ligne réservation pour détecter les conflits concurrent.
- **TTL automatique** : réservations `PENDING` expirées par un job schedulé. ✅ Déjà implémenté via `ExpireStaleReservationsJob`.
- **Status `COMPLETED`** : absent de `ReservationStatus` actuel — à ajouter pour marquer les visites passées confirmées.
- **No-show handling** : après l'heure de fin du slot, un job marque automatiquement les `CONFIRMED` passées comme `COMPLETED` (ou `NO_SHOW`).

### Notifications attendues (pattern Airbnb)

| Événement | Destinataire | Canal |
|-----------|-------------|-------|
| Réservation créée | Client | Email + DB + Push |
| Réservation créée | Bailleur | Email + DB + Push |
| Confirmation | Client | Email + DB + Push |
| Annulation | Les deux | Email + DB + Push |
| Rappel 24h avant | Client | Email + Push |
| Rappel 1h avant | Client + Bailleur | Push |
| Expiration | Client | Email + DB |
| Visite terminée → demande avis | Client | Email + Push |

✅ Notifications déjà présentes : `ReservationCreatedClientNotification`, `ReservationCreatedLandlordNotification`, `ReservationConfirmedClientNotification`, `ReservationCancelledNotification`, `ReservationExpiredNotification`.

❌ Manquant : rappels 24h/1h, `COMPLETED` status + trigger avis post-visite.

---

## 2. Calendar Sync

### Stratégie recommandée : approche en couches

```
Couche 1 (MVP)     : Bouton "Ajouter au calendrier" → liens directs Google/Outlook + .ics download
Couche 2 (V2)      : Feed .ics personnel par token → abonnement calendrier (Google/Apple/Outlook)
Couche 3 (V3)      : OAuth Google Calendar API → sync bi-directionnelle
```

### 2.1 Couche 1 — `add-to-calendar-button-react` (NPM)

**Package recommandé :** `add-to-calendar-button-react` (wrapper officiel React de `add-to-calendar-button`)

```bash
npm install add-to-calendar-button-react
```

```tsx
'use client';
import { AddToCalendarButton } from 'add-to-calendar-button-react';

<AddToCalendarButton
  name={`Visite — ${ad.title}`}
  options={['Apple', 'Google', 'Outlook.com', 'iCal']}
  location={`${ad.quarter?.name}, ${ad.quarter?.city_name}, Cameroun`}
  startDate={reservation.slot_date}            // "2026-06-15"
  startTime={reservation.slot_starts_at}        // "10:00"
  endTime={reservation.slot_ends_at}            // "11:00"
  timeZone="Africa/Douala"
  description={`Visite de bien sur KeyHome. Réf: ${reservation.id}`}
  organizer={`KeyHome|noreply@keyhome.app`}
/>
```

**Génère automatiquement :**
- Google Calendar deeplink
- Outlook.com deeplink
- Apple Calendar via `.ics` download
- Fichier `.ics` universel

### 2.2 Couche 2 — Feed .ics personnel (Laravel, `spatie/icalendar-generator`)

**Package :** `spatie/icalendar-generator` (8M+ installs, MIT, PHP 8.2+)

```bash
composer require spatie/icalendar-generator
```

```php
// GET /api/v1/my/calendar.ics?token={signed_token}
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

$reservations = TentativeReservation::where('client_id', $user->id)
    ->active()
    ->with('ad.quarter.city')
    ->get();

$calendar = Calendar::create('Mes visites KeyHome')
    ->productIdentifier('-//KeyHome//Visites//FR');

foreach ($reservations as $r) {
    $start = Carbon::parse($r->slot_date->toDateString().' '.$r->slot_starts_at)
        ->setTimezone('Africa/Douala');
    $end = Carbon::parse($r->slot_date->toDateString().' '.$r->slot_ends_at)
        ->setTimezone('Africa/Douala');

    $event = Event::create("Visite — {$r->ad->title}")
        ->uniqueIdentifier("reservation-{$r->id}@keyhome.app")
        ->startsAt($start)
        ->endsAt($end)
        ->address("{$r->ad->quarter?->name}, {$r->ad->quarter?->city?->name}, Cameroun")
        ->description("Statut : {$r->status->label()}\nRéf : {$r->id}")
        ->status($r->status === ReservationStatus::Confirmed
            ? \Spatie\IcalendarGenerator\Enums\EventStatus::Confirmed
            : \Spatie\IcalendarGenerator\Enums\EventStatus::Tentative)
        ->sequence($r->updated_at->timestamp); // incrémente à chaque mise à jour

    if ($r->status === ReservationStatus::Cancelled) {
        // STATUS:CANCELLED déclenche suppression dans Apple/Google Calendar
        $event->status(\Spatie\IcalendarGenerator\Enums\EventStatus::Cancelled);
    }

    $calendar->event($event);
}

return response($calendar->get(), 200, [
    'Content-Type' => 'text/calendar; charset=utf-8',
    'Content-Disposition' => 'inline; filename="keyhome-visites.ics"',
]);
```

**URL d'abonnement signée :**
```php
// Dans le contrôleur — URL valide 1 an, signée avec secret
$url = URL::temporarySignedRoute('calendar.ics', now()->addYear(), [
    'user' => $user->id,
]);
```

**Timezone Africa/Douala :** UTC+1 sans DST → dans le .ics :
```
DTSTART;TZID=Africa/Douala:20260615T100000
DTEND;TZID=Africa/Douala:20260615T110000
```
`spatie/icalendar-generator` gère automatiquement le VTIMEZONE component.

### 2.3 Couche 3 — Google Calendar API (OAuth2)

**Scope minimal :** `https://www.googleapis.com/auth/calendar.events`

**Flux recommandé (Next.js) :**
1. Bouton "Sync avec Google Calendar" → OAuth2 popup via `@googleapis/calendar` ou simple redirect vers `accounts.google.com/o/oauth2/auth`
2. Backend stocke `access_token` + `refresh_token` chiffrés dans `user_calendar_tokens` table
3. Après chaque création/annulation de réservation → job Laravel notifie Google Calendar via API

**Alternative plus simple (pas d'OAuth) :**
```
// Deeplink universel — pas d'OAuth, ouvre Google Calendar pré-rempli
https://calendar.google.com/calendar/render?action=TEMPLATE
  &text=Visite+KeyHome
  &dates=20260615T090000Z/20260615T100000Z
  &details=Visite+annonce+ID
  &location=Douala,+Cameroun
```

**Recommandation KeyHome Sprint 2 :** implémenter couche 1 (bouton) + couche 2 (feed .ics). OAuth Google Calendar en Sprint 3.

---

## 3. Real-Time Event-Driven

### 3.1 Architecture déjà en place dans KeyHome

Le projet utilise déjà **Laravel Reverb + Laravel Echo** pour le chat (`Events/Chat/MessageSent`, `UserTyping`, etc.), avec des `PrivateChannel` correctement sécurisés.

**Pattern existant à réutiliser pour les réservations :**

```php
// Exemple : MessageSent.php (pattern à dupliquer)
final class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }
}
```

### 3.2 Événements à broadcaster pour les réservations

| Événement Laravel | Channel | Quand |
|------------------|---------|-------|
| `ReservationCreated` | `private-user.{landlord_id}` | Client crée une réservation |
| `ReservationConfirmed` | `private-user.{client_id}` | Bailleur confirme |
| `ReservationCancelled` | `private-user.{other_party_id}` | L'une des parties annule |
| `ReservationExpired` | `private-user.{client_id}` | Job d'expiration s'exécute |
| `SlotAvailabilityChanged` | `public-ad.{ad_id}.slots` | Slot pris/libéré → mise à jour UI |

### 3.3 Pattern recommandé — `ShouldBroadcastNow` vs `ShouldBroadcast`

```php
// ShouldBroadcastNow → synchrone, immédiat (chat, confirmations urgentes)
// ShouldBroadcast    → via queue Horizon (analytics, emails, rapports)

// Pour les réservations : ShouldBroadcastNow pour les notifications temps réel
final class ReservationStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TentativeReservation $reservation,
        public readonly string $previousStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->reservation->client_id}"),
            new PrivateChannel("user.{$this->reservation->ad->user_id}"),
        ];
    }

    public function broadcastAs(): string { return 'reservation.status_changed'; }

    public function broadcastWith(): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'status' => $this->reservation->status->value,
            'status_label' => $this->reservation->status->label(),
            'previous_status' => $this->previousStatus,
            'ad_id' => $this->reservation->ad_id,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
        ];
    }
}
```

### 3.4 Next.js — Laravel Echo integration

```tsx
// hooks/useReservationRealtime.ts
'use client';
import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useEcho } from '@/hooks/useEcho'; // hook existant pour chat

export function useReservationRealtime(userId: string) {
  const queryClient = useQueryClient();
  const echo = useEcho();

  useEffect(() => {
    if (!echo || !userId) return;

    const channel = echo.private(`user.${userId}`);

    channel.listen('.reservation.status_changed', (data: {
      reservation_id: string;
      status: string;
      status_label: string;
      ad_id: string;
    }) => {
      // Invalider les queries concernées
      queryClient.invalidateQueries({ queryKey: ['reservations'] });
      queryClient.invalidateQueries({ queryKey: ['reservation', data.reservation_id] });

      // Toast notification
      // showToast(`Votre réservation est maintenant : ${data.status_label}`);
    });

    return () => { echo.leave(`private-user.${userId}`); };
  }, [echo, userId, queryClient]);
}
```

### 3.5 `SlotAvailabilityChanged` — mise à jour temps réel du calendrier

Quand un slot est réservé ou libéré, broadcaster sur un channel public de l'annonce :

```php
// Channel public — pas d'auth requise (slots sont publics)
final class SlotAvailabilityChanged implements ShouldBroadcastNow
{
    public function broadcastOn(): array
    {
        return [new Channel("ad.{$this->adId}.slots")];
    }

    public function broadcastWith(): array
    {
        return [
            'date' => $this->date,
            'starts_at' => $this->startsAt,
            'is_available' => $this->isAvailable,
        ];
    }
}
```

```tsx
// Dans ViewingBookingPanel.tsx
echo.channel(`ad.${ad.id}.slots`).listen('.slot.availability_changed', (data) => {
  queryClient.invalidateQueries({ queryKey: ['slots', ad.id, data.date] });
});
```

### 3.6 Best practices Reverb en production

- **TLS obligatoire** : `wss://` en prod (pas `ws://`). Configurer derrière Nginx reverse proxy.
- **Reconnection** : Laravel Echo gère la reconnexion automatique — pas de code custom nécessaire.
- **Channel authorization** : `routes/channels.php` — toujours vérifier ownership :
  ```php
  Broadcast::channel('user.{userId}', fn (User $user, string $userId) => $user->id === $userId);
  ```
- **ShouldBroadcastNow vs queue** : utiliser `ShouldBroadcastNow` pour notifications urgentes (réservations), `ShouldBroadcast` pour événements bas-priorité (analytics).
- **Redis pub/sub** : configurer `BROADCAST_CONNECTION=reverb` + `QUEUE_CONNECTION=redis` pour Horizon. Reverb supporte nativement Redis comme backend.
- **Offline resilience** : déjà implémenté pour le chat via `useViewingResponseSync` (IndexedDB + Background Sync API). Même pattern à appliquer aux réponses bailleur.

---

## 4. Gaps identifiés dans KeyHome

### Backend

| Gap | Priorité | Notes |
|-----|---------|-------|
| Status `COMPLETED` absent de `ReservationStatus` | Haute | Nécessaire pour le flow post-visite + avis |
| Status `NO_SHOW` absent | Moyenne | Bailleur peut marquer client absent |
| Job post-visite (auto-complétion) | Haute | Scheduler : 1h après slot_ends_at → COMPLETED |
| Rappels 24h/1h avant visite | Haute | `SendViewingReminders` scheduled command |
| Événements broadcast réservation | Haute | Aucun event Reverb pour les réservations |
| Feed `.ics` personnel | Moyenne | Route `GET /my/calendar.ics` manquante |
| Bouton "Ajouter au calendrier" | Moyenne | Frontend seulement (couche 1) |
| Idempotency key sur création réservation | Haute | Prévenir double-submit sur réseau instable |

### Frontend

| Gap | Priorité | Notes |
|-----|---------|-------|
| `useReservationRealtime` hook | Haute | Pas de real-time sur les réservations |
| `SlotAvailabilityChanged` listener | Haute | Slots ne se mettent pas à jour sans refresh |
| Bouton "Ajouter au calendrier" | Moyenne | `add-to-calendar-button-react` à intégrer |
| URL d'abonnement `.ics` copiable | Basse | Dans dashboard client |

---

## 5. Recommandations et plan d'implémentation

### Sprint 2 — Quick wins real-time (1 semaine)

1. **`ReservationStatusChanged` event** → broadcaster depuis `ConfirmReservationAction` + `ReservationService::cancel()`
2. **`SlotAvailabilityChanged` event** → broadcaster depuis `ViewingScheduleService::reserveSlot()` + `releaseSlot()`
3. **`useReservationRealtime` hook** Next.js → invalider query cache sur changement de statut
4. **Status `COMPLETED`** + job scheduler auto-complétion 1h après slot

### Sprint 3 — Calendar & Reminders (1-2 semaines)

5. **`add-to-calendar-button-react`** → intégrer dans `ViewingBookingPanel` après confirmation
6. **`spatie/icalendar-generator`** + route `.ics` + signed URL copiable dans dashboard
7. **`SendViewingReminders` command** → notification push/email 24h et 1h avant slot
8. **Idempotency key** → `X-Idempotency-Key` header sur `POST /reservations`

### Sprint 4 — Advanced (optionnel)

9. **Google Calendar OAuth sync** → bi-directional, token stocké chiffré
10. **No-show flow** → bailleur peut marquer client absent + impact trust score

---

## Packages à installer

```bash
# Backend
composer require spatie/icalendar-generator

# Frontend
npm install add-to-calendar-button-react
```

---

*Sources principales : Firecrawl agent research, spatie/icalendar-generator (packagist.org), add-to-calendar-button.com, Medium/@anishregmi19 (Laravel Reverb full guide), newsletter.systemdesign.one (Airbnb System Design)*

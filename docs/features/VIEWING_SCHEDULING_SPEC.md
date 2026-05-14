# Technical Specification — Property Viewing Scheduling & Tentative Reservation System

**Document version:** 1.0  
**Date:** 2026-03-04  
**Status:** Draft  
**Project:** KeyHome Backend (ImmoApp-Backend)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Prerequisite: Platform Compatibility — laraveljutsu/zap](#2-prerequisite-platform-compatibility)
3. [Core Concepts & Terminology](#3-core-concepts--terminology)
4. [Database Schema](#4-database-schema)
5. [Backend Architecture](#5-backend-architecture)
6. [laravel-zap Integration Design](#6-laravel-zap-integration-design)
7. [RESTful API Specification — Landlord](#7-restful-api-specification--landlord)
8. [RESTful API Specification — Client](#8-restful-api-specification--client)
9. [Business Logic & Rules](#9-business-logic--rules)
10. [Notifications & Lifecycle Events](#10-notifications--lifecycle-events)
11. [Frontend Component Design — Ad Detail Card](#11-frontend-component-design--ad-detail-card)
12. [Security](#12-security)
13. [Scalability & Performance](#13-scalability--performance)
14. [Error Handling & Validation](#14-error-handling--validation)
15. [Testing Strategy](#15-testing-strategy)
16. [Implementation Roadmap](#16-implementation-roadmap)

---

## 1. Executive Summary

This specification defines the design of a dual-interface **property viewing scheduling and tentative reservation system** for the KeyHome platform.

- **Landlords** (Bailleurs) define their viewing availability for each property listing directly from their Owner panel. They can create one-off time slots or recurring patterns (e.g. every Monday 10:00–12:00) and monitor the reservation status of each slot.
- **Clients** (Locataires / Acheteurs) browse available viewing slots directly within the Ad Detail Card of a property listing. They can tentatively reserve a slot, which signals their intent and triggers a follow-up communication workflow (phone or message) to finalise the visit. This is **explicitly not a confirmed booking**.

The scheduling engine is powered by `laraveljutsu/zap`, which provides the `HasSchedules` trait, slot availability queries, recurring patterns, and conflict detection.

---

## 2. Prerequisite: Platform Compatibility

> ⚠️ **Critical blocking constraint — must be resolved before implementation.**

### 2.1 Current Stack vs. Library Requirements

| Requirement | `laraveljutsu/zap` requires | Current project |
|---|---|---|
| PHP | ≥ 8.5 | **8.4.12** ← incompatible |
| Laravel | ≥ 13.0 | **12.x** ← incompatible |

`laraveljutsu/zap` will refuse to install on the current stack. Attempting `composer require laraveljutsu/zap` will produce a dependency resolution error.

### 2.2 Recommended Upgrade Path

The cleanest path is to upgrade the platform before implementing this feature:

```
PHP 8.4 → 8.5   (minor upgrade; no breaking changes in application code)
Laravel 12 → 13 (consult the official upgrade guide; most changes are internal)
```

**Estimated effort:** 1–2 development days for the PHP/Laravel upgrade, including full test suite validation.

**Steps:**
1. Update `Dockerfile` base image from `php:8.4-fpm-alpine` to `php:8.5-fpm-alpine`.
2. Run `composer update --dry-run` to surface any package conflicts.
3. Apply Laravel 13 upgrade guide changes (primarily `bootstrap/app.php` and new first-party package versions).
4. Run full test suite: `php artisan test`.
5. Then: `composer require laraveljutsu/zap`.

### 2.3 Alternative: Temporary Stub Layer

If an upgrade is not possible in the short term, a `ViewingScheduleService` stub can be implemented internally against existing database tables, with a defined interface that maps 1:1 to the Zap API (so it can be swapped out once the upgrade is completed). This is an interim measure and is documented in [§16 — Implementation Roadmap](#16-implementation-roadmap).

---

## 3. Core Concepts & Terminology

| Term | Definition |
|---|---|
| **Availability slot** | A time window defined by a landlord during which a property can be visited. Stored as a Zap `availability` schedule. |
| **Appointment schedule** | A Zap `appointment` schedule used to mark a slot as tentatively reserved, creating an exclusive block. |
| **Tentative reservation** | A client's pre-booking intent for a specific slot. Not a confirmed visit; requires follow-up between landlord and client. |
| **Conflict** | Two exclusive schedules overlapping the same resource (property) at the same time. |
| **Resource** | In Zap's terms, the schedulable entity — here it is the `Ad` (property listing) model. |
| **Slot duration** | Configurable per property. Default: 30 minutes. Used by `getBookableSlots()`. |
| **Buffer time** | Time between consecutive slots. Default: 0 minutes. Configurable. |

---

## 4. Database Schema

### 4.1 Existing tables (unchanged)

```
users           — id (uuid), role (UserRole enum), name, email, …
ads             — id (uuid), user_id (landlord), title, status, …
```

### 4.2 Tables added by laraveljutsu/zap (auto-migrated)

```sql
-- Published via: php artisan vendor:publish --tag=zap-migrations

schedules
  id                  uuid PK       (requires UUID migration variant — see §2.2)
  schedulable_type    string        e.g. "App\Models\Ad"
  schedulable_id      uuid          references ads.id
  name                string        human label e.g. "Weekend Morning Slots"
  type                string        availability | appointment | blocked | custom
  frequency_type      string        once | daily | weekly | monthly | …
  frequency_config    json          { "days": ["monday","wednesday"] }
  starts_on           date nullable
  ends_on             date nullable
  metadata            json nullable  { "slot_duration": 30, "buffer": 0, "reserved_by": "<uuid>" }
  all_day             boolean
  overlap_allowed     boolean
  created_at / updated_at

schedule_periods
  id                  uuid PK
  schedule_id         uuid FK → schedules.id
  starts_at           time   e.g. "10:00"
  ends_at             time   e.g. "12:00"
  created_at / updated_at
```

> **UUID note:** All project models use `HasUuids`. The Zap migrations must be published and manually modified to use `uuid('id')->primary()`, `uuidMorphs('schedulable')`, and `foreignUuid('schedule_id')` before running `php artisan migrate`. See §2.2 of the Zap README.

### 4.3 New table: `tentative_reservations`

This table is owned by the application (not Zap) and is the authoritative source of reservation intent.

```sql
tentative_reservations
  id                  uuid PK
  ad_id               uuid FK → ads.id
  client_id           uuid FK → users.id
  appointment_schedule_id  uuid FK → schedules.id   (the Zap appointment schedule)
  slot_date           date                           e.g. 2026-04-07
  slot_starts_at      time                           e.g. 10:00
  slot_ends_at        time                           e.g. 10:30
  status              enum(pending, confirmed, cancelled, expired)
  client_message      text nullable                  optional note from client
  landlord_notes      text nullable
  cancelled_by        enum(client, landlord) nullable
  cancellation_reason text nullable
  expires_at          timestamp                      auto-set to slot_date + 24h
  notified_at         timestamp nullable             landlord notified timestamp
  created_at / updated_at
  deleted_at          timestamp nullable             (soft delete)

  UNIQUE KEY (ad_id, slot_date, slot_starts_at, status WHERE status IN ('pending', 'confirmed'))
```

### 4.4 Entity Relationship Summary

```
User (landlord) ──┐
                  ├── Ad ── HasSchedules (Zap) ── schedules ── schedule_periods
User (client)  ──┘              │
                                └── tentative_reservations ── schedules (appointment)
```

### 4.5 New enum: `ReservationStatus`

```php
// app/Enums/ReservationStatus.php
enum ReservationStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired   = 'expired';
}
```

---

## 5. Backend Architecture

### 5.1 Directory Structure

```
app/
  Models/
    TentativeReservation.php       ← new
  Enums/
    ReservationStatus.php          ← new
    CancelledBy.php                ← new
  Services/
    ViewingScheduleService.php     ← new  (wraps Zap facade)
    ReservationService.php         ← new  (handles reservation lifecycle)
  Http/
    Controllers/Api/V1/
      ViewingAvailabilityController.php   ← new (landlord)
      ViewingReservationController.php    ← new (client)
    Requests/
      StoreAvailabilityRequest.php        ← new
      UpdateAvailabilityRequest.php       ← new
      StoreTentativeReservationRequest.php ← new
      CancelReservationRequest.php        ← new
    Resources/
      AvailabilitySlotResource.php        ← new
      BookableSlotResource.php            ← new
      TentativeReservationResource.php    ← new
  Observers/
    TentativeReservationObserver.php      ← new
  Notifications/
    ReservationCreatedLandlordNotification.php  ← new
    ReservationCreatedClientNotification.php   ← new
    ReservationCancelledNotification.php       ← new
    ReservationExpiredNotification.php         ← new
  Jobs/
    ExpireStaleReservationsJob.php             ← new
  Policies/
    ViewingAvailabilityPolicy.php              ← new
    TentativeReservationPolicy.php             ← new
```

### 5.2 Design Patterns

| Pattern | Usage |
|---|---|
| **Service Layer** | `ViewingScheduleService` wraps all Zap interactions. Controllers never call the Zap facade directly. |
| **Repository Pattern** | `TentativeReservation::query()` is accessed only through `ReservationService`; never raw in controllers. |
| **Observer Pattern** | `TentativeReservationObserver` fires on `created`, `updated`, `deleted` to dispatch notifications and Zap cleanup. |
| **Form Request Validation** | All input goes through dedicated Form Request classes before reaching services. |
| **API Resource** | All responses are wrapped in Eloquent API Resources to decouple database shape from API contract. |
| **Policy** | Laravel policies enforce ownership rules (landlord can only modify their own property's availability). |
| **Queued Jobs** | `ExpireStaleReservationsJob` runs via the scheduler to expire `pending` reservations past their `expires_at`. |

---

## 6. laravel-zap Integration Design

### 6.1 Making `Ad` Schedulable

```php
// app/Models/Ad.php
use Zap\Models\Concerns\HasSchedules;

class Ad extends Model
{
    use HasUuids, HasSchedules, SoftDeletes, …;
}
```

### 6.2 ViewingScheduleService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use Carbon\Carbon;
use Zap\Facades\Zap;
use Zap\Models\Schedule;

final class ViewingScheduleService
{
    /**
     * Create a one-off or recurring availability schedule for a property.
     *
     * @param array{
     *   name: string,
     *   starts_on: string,
     *   ends_on: string|null,
     *   periods: list<array{starts_at: string, ends_at: string}>,
     *   recurrence: string|null,
     *   recurrence_days: list<string>|null,
     *   slot_duration: int,
     *   buffer_minutes: int,
     * } $data
     */
    public function createAvailability(Ad $ad, array $data): Schedule
    {
        $builder = Zap::for($ad)
            ->named($data['name'])
            ->availability()
            ->noOverlap()
            ->withMetadata([
                'slot_duration'  => $data['slot_duration'] ?? 30,
                'buffer_minutes' => $data['buffer_minutes'] ?? 0,
            ]);

        foreach ($data['periods'] as $period) {
            $builder->addPeriod($period['starts_at'], $period['ends_at']);
        }

        $this->applyDateRange($builder, $data);
        $this->applyRecurrence($builder, $data);

        return $builder->save();
    }

    /**
     * Reserve a bookable slot by creating an appointment schedule (exclusive).
     *
     * @param array{date: string, starts_at: string, ends_at: string, metadata: array} $data
     */
    public function reserveSlot(Ad $ad, array $data): Schedule
    {
        return Zap::for($ad)
            ->named('Tentative Viewing — ' . $data['date'])
            ->appointment()
            ->noOverlap()
            ->from($data['date'])
            ->addPeriod($data['starts_at'], $data['ends_at'])
            ->withMetadata($data['metadata'])
            ->save();
    }

    /**
     * Release a reserved slot by deleting its appointment schedule.
     */
    public function releaseSlot(Schedule $appointmentSchedule): void
    {
        $appointmentSchedule->delete();
    }

    /**
     * Return bookable slots for a given date.
     *
     * @return list<array{starts_at: string, ends_at: string, is_available: bool}>
     */
    public function getBookableSlotsForDate(Ad $ad, string $date): array
    {
        $meta = $this->getAvailabilityMetadata($ad);

        return $ad->getBookableSlots($date, $meta['slot_duration'], $meta['buffer_minutes']);
    }

    /**
     * Return bookable slots for a date range (used for calendar month view).
     *
     * @return array<string, list<array{starts_at: string, ends_at: string}>>
     */
    public function getBookableSlotsForRange(Ad $ad, string $from, string $to): array
    {
        $meta  = $this->getAvailabilityMetadata($ad);
        $slots = [];

        $current = Carbon::parse($from);
        $end     = Carbon::parse($to);

        while ($current->lte($end)) {
            $dateStr = $current->toDateString();
            $daySlots = $ad->getBookableSlots($dateStr, $meta['slot_duration'], $meta['buffer_minutes']);

            if (! empty($daySlots)) {
                $slots[$dateStr] = $daySlots;
            }

            $current->addDay();
        }

        return $slots;
    }

    /**
     * Detect whether a proposed period conflicts with an existing schedule.
     */
    public function hasConflict(Schedule $schedule): bool
    {
        return Zap::hasConflicts($schedule);
    }

    // -------------------------------------------------------------------------

    private function applyDateRange(mixed $builder, array $data): void
    {
        if (isset($data['ends_on'])) {
            $builder->from($data['starts_on'])->to($data['ends_on']);
        } else {
            $builder->from($data['starts_on']);
        }
    }

    private function applyRecurrence(mixed $builder, array $data): void
    {
        match ($data['recurrence'] ?? 'once') {
            'daily'    => $builder->daily(),
            'weekly'   => $builder->weekly($data['recurrence_days'] ?? []),
            'biweekly' => $builder->biweekly($data['recurrence_days'] ?? []),
            'monthly'  => $builder->monthly(['days_of_month' => $data['days_of_month'] ?? []]),
            default    => null,
        };
    }

    /** @return array{slot_duration: int, buffer_minutes: int} */
    private function getAvailabilityMetadata(Ad $ad): array
    {
        $latestSchedule = $ad->availabilitySchedules()->latest()->first();

        return [
            'slot_duration'  => $latestSchedule?->metadata['slot_duration'] ?? 30,
            'buffer_minutes' => $latestSchedule?->metadata['buffer_minutes'] ?? 0,
        ];
    }
}
```

---

## 7. RESTful API Specification — Landlord

All endpoints are prefixed `/api/v1` and require `auth:sanctum` + ownership authorization.

### 7.1 List availability schedules for a property

```
GET /api/v1/ads/{ad}/availability
Authorization: Bearer {token}
```

**Query params:**

| Param | Type | Default | Description |
|---|---|---|---|
| `from` | date | today | Start of window |
| `to` | date | +30 days | End of window |

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Weekend Mornings",
      "type": "availability",
      "frequency": "weekly",
      "frequency_days": ["saturday", "sunday"],
      "starts_on": "2026-04-01",
      "ends_on": "2026-12-31",
      "periods": [
        { "starts_at": "09:00", "ends_at": "12:00" }
      ],
      "slot_duration": 30,
      "buffer_minutes": 0,
      "upcoming_slots_count": 48
    }
  ]
}
```

---

### 7.2 Create availability schedule

```
POST /api/v1/ads/{ad}/availability
Authorization: Bearer {token}
Content-Type: application/json
```

**Request body:**
```json
{
  "name": "Weekday mornings",
  "starts_on": "2026-04-07",
  "ends_on": "2026-12-31",
  "periods": [
    { "starts_at": "10:00", "ends_at": "12:00" }
  ],
  "recurrence": "weekly",
  "recurrence_days": ["monday", "wednesday"],
  "slot_duration": 30,
  "buffer_minutes": 10
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | ✓ | max:100 |
| `starts_on` | date | ✓ | ≥ today |
| `ends_on` | date | — | ≥ starts_on, max 2 years |
| `periods` | array | ✓ | 1–4 periods per schedule |
| `periods[].starts_at` | time HH:MM | ✓ | |
| `periods[].ends_at` | time HH:MM | ✓ | ends_at > starts_at |
| `recurrence` | enum | — | `once`, `daily`, `weekly`, `biweekly`, `monthly` |
| `recurrence_days` | string[] | if weekly/biweekly | `monday`…`sunday` |
| `days_of_month` | int[] | if monthly | 1–31 |
| `slot_duration` | int | — | minutes, default 30, min 15, max 240 |
| `buffer_minutes` | int | — | default 0, max 60 |

**Response 201:**
```json
{
  "data": { /* AvailabilitySlotResource */ },
  "message": "Availability schedule created successfully."
}
```

**Errors:**

| Code | Reason |
|---|---|
| 422 | Validation failure |
| 403 | Authenticated user doesn't own this Ad |
| 409 | Proposed schedule conflicts with an existing one |

---

### 7.3 Update availability schedule

```
PUT /api/v1/ads/{ad}/availability/{schedule}
Authorization: Bearer {token}
```

Same body as POST. Partial update is supported (only send changed fields).

**Response 200:** Updated `AvailabilitySlotResource`.

> **Note:** If active tentative reservations exist for this schedule, only `name`, `ends_on`, `slot_duration`, and `buffer_minutes` may be changed. Changing `periods` or `recurrence` on a schedule with live reservations returns `409 Conflict` with a list of affected reservations.

---

### 7.4 Delete availability schedule

```
DELETE /api/v1/ads/{ad}/availability/{schedule}
Authorization: Bearer {token}
```

**Response 204.**

> Triggers `ReservationCancelledNotification` for all `pending` reservations linked to this schedule (via Observer).

---

### 7.5 View slot status calendar (landlord dashboard)

```
GET /api/v1/ads/{ad}/availability/calendar
Authorization: Bearer {token}
```

**Query params:** `from` (date), `to` (date, max +90 days).

**Response 200:**
```json
{
  "data": {
    "2026-04-07": {
      "slots": [
        {
          "starts_at": "10:00",
          "ends_at": "10:30",
          "status": "available"
        },
        {
          "starts_at": "10:30",
          "ends_at": "11:00",
          "status": "tentatively_reserved",
          "reservation": {
            "id": "uuid",
            "client_name": "Jean Dupont",
            "client_message": "I'd like to visit with my partner.",
            "reserved_at": "2026-04-04T14:32:00Z",
            "expires_at": "2026-04-05T14:32:00Z"
          }
        }
      ]
    }
  }
}
```

---

### 7.6 List reservations for a property

```
GET /api/v1/ads/{ad}/reservations
Authorization: Bearer {token}
```

**Query params:** `status` (pending|confirmed|cancelled|expired), `from`, `to`, `page`.

**Response 200:** Paginated list of `TentativeReservationResource`.

---

## 8. RESTful API Specification — Client

### 8.1 Get available slots for a property

```
GET /api/v1/ads/{ad}/slots
Authorization: Bearer {token}   (optional — unauthenticated clients see the same slots)
```

**Query params:**

| Param | Type | Default |
|---|---|---|
| `from` | date | today |
| `to` | date | today + 14 days |

**Response 200:**
```json
{
  "data": {
    "ad_id": "uuid",
    "slot_duration_minutes": 30,
    "slots_by_date": {
      "2026-04-07": [
        { "starts_at": "10:00", "ends_at": "10:30", "is_available": true },
        { "starts_at": "10:30", "ends_at": "11:00", "is_available": false }
      ],
      "2026-04-09": [
        { "starts_at": "10:00", "ends_at": "10:30", "is_available": true }
      ]
    }
  }
}
```

Slots with `is_available: false` are already tentatively reserved but are shown so the client understands the schedule shape. No client identity is disclosed.

---

### 8.2 Tentatively reserve a slot

```
POST /api/v1/ads/{ad}/reservations
Authorization: Bearer {token}   (required)
Content-Type: application/json
```

**Request body:**
```json
{
  "slot_date": "2026-04-07",
  "slot_starts_at": "10:00",
  "slot_ends_at": "10:30",
  "client_message": "Je souhaite visiter avec mon conjoint le matin."
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `slot_date` | date | ✓ | ≥ today |
| `slot_starts_at` | time HH:MM | ✓ | Must be start of a valid slot |
| `slot_ends_at` | time HH:MM | ✓ | = starts_at + slot_duration |
| `client_message` | string | — | max:500 |

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "ad": { "id": "uuid", "title": "Bel appartement T3 — Cocody" },
    "slot_date": "2026-04-07",
    "slot_starts_at": "10:00",
    "slot_ends_at": "10:30",
    "status": "pending",
    "expires_at": "2026-04-08T10:00:00Z",
    "next_steps": "Votre créneau est retenu. Le propriétaire vous contactera pour confirmer la visite. Assurez-vous d'être joignable au numéro enregistré sur votre compte."
  },
  "message": "Votre réservation provisoire a bien été enregistrée."
}
```

**Errors:**

| Code | Reason |
|---|---|
| 401 | Unauthenticated |
| 403 | Client is the landlord of this property |
| 409 | Slot already reserved (race condition) |
| 410 | Slot date is in the past |
| 422 | Validation failure (invalid time, unknown slot) |

---

### 8.3 View client's own reservations

```
GET /api/v1/my/reservations
Authorization: Bearer {token}
```

**Query params:** `status`, `page`.

**Response 200:** Paginated list of `TentativeReservationResource`.

---

### 8.4 Cancel a tentative reservation

```
DELETE /api/v1/reservations/{reservation}
Authorization: Bearer {token}
```

Accessible by both the client (own reservation) and the landlord (any reservation on their property). The `cancelled_by` field is set accordingly.

**Request body (optional):**
```json
{ "cancellation_reason": "Je ne suis plus disponible ce jour-là." }
```

**Response 200:**
```json
{
  "data": { /* TentativeReservationResource with status: cancelled */ },
  "message": "Réservation provisoire annulée."
}
```

---

## 9. Business Logic & Rules

### 9.1 Slot Availability Rules

1. A slot is `available` if: no `pending` or `confirmed` `TentativeReservation` exists for `(ad_id, slot_date, slot_starts_at)`.
2. Zap enforces exclusivity at the schedule level via `->noOverlap()` on appointment schedules. The application provides a second-layer check via the `UNIQUE KEY` on `tentative_reservations` before calling Zap.
3. A slot is only bookable if the underlying availability schedule's `frequency_config` places it on that date, and no blocked schedule overlaps.
4. Slots **cannot** be reserved for dates in the past.
5. A landlord **cannot** reserve their own property's slots.

### 9.2 Reservation Lifecycle

```
[available]
     │
     │  client POSTs reservation
     ▼
[pending] ──── expires_at reached ──────────────────► [expired]
     │                                                     │
     │  landlord confirms (direct call / message)          │
     │  (manual state change via Filament panel            │
     │   or future PATCH endpoint)                         │
     ▼                                                     │
[confirmed] ──── landlord/client cancels ──────────► [cancelled]
     │
     │  landlord/client cancels
     ▼
[cancelled]
```

- **Expiry:** `ExpireStaleReservationsJob` runs every 30 minutes (via Laravel's scheduler). It transitions `pending` reservations whose `expires_at < now()` to `expired` and releases the corresponding Zap appointment schedule.
- **Default TTL:** 24 hours from the time of reservation creation.
- **Auto-release:** When a reservation reaches `expired` or `cancelled`, its associated appointment schedule (in `schedules` table) is deleted by `TentativeReservationObserver`.

### 9.3 Concurrency / Race Condition Protection

The unique constraint `(ad_id, slot_date, slot_starts_at, status IN ('pending','confirmed'))` is enforced at the database level. The `ReservationService::reserve()` method wraps the insert in `DB::transaction()` and catches `UniqueConstraintViolationException`, returning a `409 Conflict` response.

```php
// ReservationService::reserve()
try {
    DB::transaction(function () use ($ad, $data, $client): TentativeReservation {
        // 1. Re-verify slot is still available inside transaction  (SELECT … FOR UPDATE)
        $this->assertSlotIsAvailable($ad, $data);

        // 2. Create Zap appointment (exclusive block)
        $appointmentSchedule = $this->viewingScheduleService->reserveSlot($ad, [
            'date'       => $data['slot_date'],
            'starts_at'  => $data['slot_starts_at'],
            'ends_at'    => $data['slot_ends_at'],
            'metadata'   => ['reserved_by' => $client->id],
        ]);

        // 3. Persist tentative_reservation record
        return TentativeReservation::query()->create([
            'ad_id'                   => $ad->id,
            'client_id'               => $client->id,
            'appointment_schedule_id' => $appointmentSchedule->id,
            'slot_date'               => $data['slot_date'],
            'slot_starts_at'          => $data['slot_starts_at'],
            'slot_ends_at'            => $data['slot_ends_at'],
            'status'                  => ReservationStatus::Pending,
            'client_message'          => $data['client_message'] ?? null,
            'expires_at'              => now()->addHours(24),
        ]);
    });
} catch (UniqueConstraintViolationException) {
    throw new SlotAlreadyReservedException();
}
```

### 9.4 Availability Update Rules

- If a landlord shortens the `ends_on` of an availability schedule such that existing `pending` reservations fall outside the new range, the system **does not** auto-cancel them. Instead, the API returns a `409` listing the affected reservations and requires explicit acknowledgement to proceed.
- Deleting an availability schedule **does** auto-cancel all linked `pending` reservations and notifies clients.

---

## 10. Notifications & Lifecycle Events

### 10.1 Notification Matrix

| Event | Recipient | Channel |
|---|---|---|
| Reservation created | Landlord | Database + Push (WebPush) |
| Reservation created | Client | Database |
| Reservation cancelled by client | Landlord | Database + Push |
| Reservation cancelled by landlord | Client | Database + Push |
| Reservation expired | Client | Database |
| Availability schedule deleted (with active reservations) | Client | Database + Push |

### 10.2 Observer

```php
// TentativeReservationObserver — registered in AppServiceProvider

public function created(TentativeReservation $reservation): void
{
    // Notify landlord
    $reservation->ad->user->notify(new ReservationCreatedLandlordNotification($reservation));
    // Notify client
    $reservation->client->notify(new ReservationCreatedClientNotification($reservation));
}

public function updated(TentativeReservation $reservation): void
{
    if ($reservation->wasChanged('status')) {
        match ($reservation->status) {
            ReservationStatus::Cancelled => $this->notifyCancellation($reservation),
            ReservationStatus::Expired   => $this->notifyExpiry($reservation),
            default                      => null,
        };
    }
}

public function deleted(TentativeReservation $reservation): void
{
    // Release Zap appointment schedule when reservation is hard-deleted
    if ($reservation->appointmentSchedule) {
        $reservation->appointmentSchedule->delete();
    }
}
```

### 10.3 Scheduled Job

```php
// routes/console.php
Schedule::job(ExpireStaleReservationsJob::class)->everyThirtyMinutes();
```

---

## 11. Frontend Component Design — Ad Detail Card

### 11.1 Context

The scheduling UI lives inside the existing `AdDetailCard` component of the `keyhome-frontend-next` (Next.js) application. It is a new collapsible section titled **"Planifier une visite"** positioned below the property description.

### 11.2 Component Tree

```
AdDetailCard
└── ViewingSchedulerSection           (new)
    ├── ViewingCalendar               (date picker, highlights available days)
    ├── SlotGrid                      (time slot buttons for selected date)
    │   └── SlotButton                (available | reserved | selected)
    ├── ReservationForm               (client message + confirm CTA)
    │   └── ConfirmReservationButton
    └── ReservationConfirmation       (post-submit success state)
        └── NextStepsCard
```

### 11.3 State Management (React Query + TanStack)

```typescript
// hooks/useViewingSlots.ts
export function useViewingSlots(adId: string, from: string, to: string) {
  return useQuery({
    queryKey: ['viewing-slots', adId, from, to],
    queryFn:  () => api.get(`/ads/${adId}/slots`, { params: { from, to } }),
    staleTime: 60_000,   // 60s — slots are refetched on window focus
    refetchOnWindowFocus: true,
  });
}

// hooks/useCreateReservation.ts
export function useCreateReservation(adId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: ReservationPayload) =>
      api.post(`/ads/${adId}/reservations`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['viewing-slots', adId] });
    },
  });
}
```

### 11.4 UI States

| State | Display |
|---|---|
| Loading slots | Skeleton calendar + slot grid shimmer |
| No availability defined | "Ce propriétaire n'a pas encore défini ses disponibilités." |
| Date with no slots | "Aucun créneau disponible ce jour-là." |
| Slot selected | Slot highlighted in teal (#0D9488) |
| Submitting | Confirm button shows spinner, form disabled |
| Success | Green confirmation card with next steps message |
| `409` conflict | Inline error: "Ce créneau vient d'être réservé. Veuillez en choisir un autre." → slot refetched |
| `401` unauthenticated | Prompt to log in before reserving |

### 11.5 Accessibility (WCAG 2.1 AA)

- All slot buttons include `aria-label="Créneau du [date] de [start] à [end], [disponible/réservé]"`.
- Selected slot conveyed via `aria-pressed="true"`.
- Calendar navigation is keyboard-accessible (`←→` to change dates, `Enter` to select).
- Colour is not the sole differentiator: available slots have a checkmark icon; reserved slots have a lock icon.
- Focus ring uses the global `focus-visible` style (`ring-2 ring-teal-500`).
- Submission error messages are associated to form via `aria-describedby`.

### 11.6 Responsive Behaviour

| Viewport | Layout |
|---|---|
| Mobile (< 640px) | Calendar is a horizontal scroll week-strip; SlotGrid is a 2-column grid |
| Tablet (640–1024px) | Calendar is a compact month view; SlotGrid is a 3-column grid |
| Desktop (> 1024px) | Side-by-side: calendar left, slot grid + form right |

---

## 12. Security

### 12.1 Authentication & Authorization

| Action | Required role | Policy check |
|---|---|---|
| Create/update/delete availability | Authenticated landlord | `ViewingAvailabilityPolicy::update($user, $ad)` — `$user->id === $ad->user_id` |
| View availability calendar (landlord view) | Authenticated landlord | Same |
| View public slots | Any (no auth required) | — |
| Create tentative reservation | Authenticated (any non-landlord) | `TentativeReservationPolicy::create($user, $ad)` — `$user->id !== $ad->user_id` |
| Cancel reservation | Client (own) or landlord (property's) | `TentativeReservationPolicy::cancel($user, $reservation)` |

### 12.2 Rate Limiting

| Endpoint | Limit |
|---|---|
| `GET /ads/{ad}/slots` | 60 req/min (throttle:60,1) |
| `POST /ads/{ad}/reservations` | 5 req/min (throttle:5,1) |
| `POST /ads/{ad}/availability` | 20 req/min (throttle:20,1) |
| `DELETE /reservations/{id}` | 20 req/min (throttle:20,1) |

### 12.3 CSRF

All mutating API endpoints are protected by Sanctum token authentication (`auth:sanctum`). The Next.js frontend sends the `Authorization: Bearer` header on all mutating requests; no cookie-based CSRF is required for the API. The Filament Bailleur panel (web routes) continues to use Laravel's `VerifyCsrfToken` mechanism.

### 12.4 Input Sanitization / SQL Injection

- All user-supplied strings go through Form Request validation before reaching the service layer.
- Eloquent ORM is used exclusively (no raw `DB::` queries in new code).
- Zap internally uses Eloquent; no raw SQL is introduced.

### 12.5 Data Privacy

- Client identity (name, phone) is **never exposed** in the public slots endpoint (`GET /ads/{ad}/slots`).
- The landlord calendar endpoint reveals only that a slot is `tentatively_reserved` and the client's display name — no contact details are returned in the API response. Contact details are visible only inside the Filament Bailleur panel.

---

## 13. Scalability & Performance

### 13.1 Query Optimisation

- `getBookableSlotsForRange()` iterates day by day over the requested window. For large windows (> 30 days), calls should be debounced on the frontend; month-at-a-time is the intended usage pattern.
- Availability schedules are eager-loaded with their `periods` relation in `ViewingAvailabilityController::index()` to prevent N+1.
- Add composite indexes:

```sql
-- tentative_reservations
CREATE INDEX idx_tr_ad_date ON tentative_reservations (ad_id, slot_date, status);
CREATE INDEX idx_tr_client ON tentative_reservations (client_id, status);

-- schedules (added to Zap's published migration)
CREATE INDEX idx_schedules_schedulable ON schedules (schedulable_type, schedulable_id, type);
```

### 13.2 Caching

- `GET /ads/{ad}/slots` responses are cached in Redis with key `slots:{ad_id}:{from}:{to}`, TTL 60 seconds. The cache is invalidated when: (a) an availability schedule is created/updated/deleted, or (b) a reservation is created/cancelled on that ad.
- `Cache::tags(['slots', "ad:{$ad->id}"])->flush()` in the Observer handles invalidation.

### 13.3 Queue Workers

- All notifications are queued (implement `ShouldQueue` on each notification class).
- `ExpireStaleReservationsJob` is lightweight (single batch update query), but still dispatched to the queue to avoid scheduler clock drift.

### 13.4 Calendar Rendering

- The frontend fetches slot data per-month on calendar navigation, not per-day, to reduce API round-trips.
- Slot data for the current + next month is prefetched on component mount.

---

## 14. Error Handling & Validation

### 14.1 Server-side Form Request: StoreAvailabilityRequest

```php
public function rules(): array
{
    return [
        'name'                    => ['required', 'string', 'max:100'],
        'starts_on'               => ['required', 'date', 'after_or_equal:today'],
        'ends_on'                 => ['nullable', 'date', 'after:starts_on', 'before:' . now()->addYears(2)->toDateString()],
        'periods'                 => ['required', 'array', 'min:1', 'max:4'],
        'periods.*.starts_at'     => ['required', 'date_format:H:i'],
        'periods.*.ends_at'       => ['required', 'date_format:H:i', 'after:periods.*.starts_at'],
        'recurrence'              => ['nullable', Rule::in(['once', 'daily', 'weekly', 'biweekly', 'monthly'])],
        'recurrence_days'         => ['required_if:recurrence,weekly,biweekly', 'array'],
        'recurrence_days.*'       => [Rule::in(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])],
        'slot_duration'           => ['nullable', 'integer', 'min:15', 'max:240'],
        'buffer_minutes'          => ['nullable', 'integer', 'min:0', 'max:60'],
    ];
}
```

### 14.2 Server-side Form Request: StoreTentativeReservationRequest

```php
public function rules(): array
{
    return [
        'slot_date'       => ['required', 'date', 'after_or_equal:today'],
        'slot_starts_at'  => ['required', 'date_format:H:i'],
        'slot_ends_at'    => ['required', 'date_format:H:i', 'after:slot_starts_at'],
        'client_message'  => ['nullable', 'string', 'max:500'],
    ];
}
```

### 14.3 Custom Exception Handling

```php
// Custom exceptions
SlotAlreadyReservedException     → HTTP 409
SlotNotAvailableException        → HTTP 410
ScheduleHasActiveReservationsException → HTTP 409
SelfReservationException         → HTTP 403
```

These are registered in `bootstrap/app.php` via `withExceptions()` and render JSON:

```json
{
  "error": {
    "code": "SLOT_ALREADY_RESERVED",
    "message": "Ce créneau vient d'être réservé par un autre utilisateur.",
    "hint": "Veuillez sélectionner un autre créneau disponible."
  }
}
```

---

## 15. Testing Strategy

### 15.1 Feature Tests (Pest)

```
tests/Feature/
  ViewingAvailability/
    CreateAvailabilityTest.php     — happy path, validation, 403 ownership
    UpdateAvailabilityTest.php     — partial update, conflict detection
    DeleteAvailabilityTest.php     — cascading reservation cancellation
    GetCalendarTest.php            — slot status display
  ViewingReservation/
    GetSlotsTest.php               — public endpoint, date range
    CreateReservationTest.php      — happy path, conflict 409, self-booking 403
    CancelReservationTest.php      — client cancel, landlord cancel
  Jobs/
    ExpireStaleReservationsTest.php — TTL expiry, Zap schedule release
```

### 15.2 Unit Tests

```
tests/Unit/
  Services/
    ViewingScheduleServiceTest.php  — Zap facade mocked
    ReservationServiceTest.php      — concurrency, lifecycle
```

### 15.3 Key Assertions

- Reserving a slot twice returns 409 and leaves only one `TentativeReservation` record.
- Deleting an availability schedule transitions all linked `pending` reservations to `cancelled`.
- `ExpireStaleReservationsJob` deletes the Zap appointment schedule after expiry.
- Public slots endpoint does not expose client PII.
- Landlord cannot create a reservation on their own ad.

---

## 16. Implementation Roadmap

### Phase 0: Platform Upgrade (prerequisite, ~2 days)

- [ ] Upgrade PHP 8.4 → 8.5 in `Dockerfile` and local devcontainer.
- [ ] Upgrade Laravel 12 → 13 (`composer update laravel/framework`).
- [ ] Run `php artisan test` — fix all regressions.
- [ ] Commit and push; validate GitLab CI passes.

### Phase 1: Core Backend (~4 days)

- [ ] `composer require laraveljutsu/zap`
- [ ] Publish and adapt Zap migrations for UUIDs.
- [ ] Create `TentativeReservation` model + migration + enum.
- [ ] Add `HasSchedules` to `Ad` model.
- [ ] Implement `ViewingScheduleService` + `ReservationService`.
- [ ] Implement Policies: `ViewingAvailabilityPolicy`, `TentativeReservationPolicy`.
- [ ] Implement Form Requests + custom exceptions.
- [ ] Implement Controllers + API Resources.
- [ ] Register routes in `routes/api.php`.
- [ ] Register Observer in `AppServiceProvider`.
- [ ] Write feature + unit tests.
- [ ] Run `vendor/bin/pint --dirty`.

### Phase 2: Notifications & Jobs (~1 day)

- [ ] Implement all 4 notification classes (queued, ShouldQueue).
- [ ] Implement `ExpireStaleReservationsJob`.
- [ ] Register scheduler in `routes/console.php`.
- [ ] Test notification dispatch in feature tests.

### Phase 3: Filament Bailleur Panel — Availability Management (~2 days)

- [ ] Create `ViewingAvailabilityResource` or a dedicated `ManageAvailability` page under the Bailleur panel.
- [ ] Calendar widget showing slot status.
- [ ] CRUD for availability schedules.
- [ ] Reservation list view per property.

### Phase 4: Frontend — Ad Detail Card Component (~3 days)

- [ ] Add `useViewingSlots` + `useCreateReservation` hooks.
- [ ] Build `ViewingSchedulerSection`, `ViewingCalendar`, `SlotGrid`, `SlotButton`.
- [ ] Build `ReservationForm` + `ReservationConfirmation`.
- [ ] Implement WCAG 2.1 AA accessibility attributes.
- [ ] Responsive layout (mobile / tablet / desktop).
- [ ] E2E test with Playwright (happy path + 409 handling).

### Phase 4b (optional): Interim Stub — if upgrade is blocked

If Phase 0 (platform upgrade) cannot be completed immediately, implement a `ViewingScheduleStub` that:
- Stores availability windows directly in a `property_availability_windows` table (start_date, end_date, days_of_week[], start_time, end_time, slot_duration).
- Exposes the same interface as `ViewingScheduleService` (same method signatures).
- Is swapped out for the real Zap-backed service once the upgrade is done, with zero changes required in the controllers or frontend.

---

*End of specification.*

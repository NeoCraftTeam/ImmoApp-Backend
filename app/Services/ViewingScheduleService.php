<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\Zap\Schedule;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Carbon\Carbon;
use Zap\Facades\Zap;

final class ViewingScheduleService implements ViewingScheduleServiceInterface
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
     *   days_of_month: list<int>|null,
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
                'slot_duration' => $data['slot_duration'],
                'buffer_minutes' => $data['buffer_minutes'],
            ]);

        foreach ($data['periods'] as $period) {
            $builder->addPeriod($period['starts_at'], $period['ends_at']);
        }

        $this->applyDateRange($builder, $data);
        $this->applyRecurrence($builder, $data);

        /** @var Schedule $saved */
        $saved = $builder->save();

        return $saved;
    }

    /**
     * Update an existing availability schedule (replaces periods and recurrence).
     *
     * @param array{
     *   name?: string,
     *   starts_on?: string,
     *   ends_on?: string|null,
     *   periods?: list<array{starts_at: string, ends_at: string}>,
     *   recurrence?: string|null,
     *   recurrence_days?: list<string>|null,
     *   days_of_month?: list<int>|null,
     *   slot_duration?: int,
     *   buffer_minutes?: int,
     * } $data
     */
    public function updateAvailability(Ad $ad, Schedule $schedule, array $data): Schedule
    {
        // Delete old schedule and recreate to reset periods via Zap builder.
        $schedule->delete();

        $merged = array_merge([
            'name' => $schedule->name,
            'starts_on' => $schedule->start_date->toDateString(),
            'ends_on' => $schedule->end_date?->toDateString(),
            'periods' => $schedule->periods->map(fn ($p): array => [
                'starts_at' => $p->start_time->format('H:i'),
                'ends_at' => $p->end_time->format('H:i'),
            ])->toArray(),
            'recurrence' => null,
            'slot_duration' => $schedule->metadata['slot_duration'] ?? 30,
            'buffer_minutes' => $schedule->metadata['buffer_minutes'] ?? 0,
        ], $data);

        return $this->createAvailability($ad, $merged);
    }

    /**
     * Reserve a bookable slot by creating an appointment schedule (exclusive).
     *
     * @param  array{date: string, starts_at: string, ends_at: string, metadata: array<string, mixed>}  $data
     */
    public function reserveSlot(Ad $ad, array $data): Schedule
    {
        /** @var Schedule $saved */
        $saved = Zap::for($ad)
            ->named('Visite provisoire — '.$data['date'])
            ->appointment()
            ->noOverlap()
            ->from($data['date'])
            ->addPeriod($data['starts_at'], $data['ends_at'])
            ->withMetadata($data['metadata'])
            ->save();

        return $saved;
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
     * Return bookable slots grouped by date for a date range (calendar view).
     *
     * @return array<string, list<array{starts_at: string, ends_at: string, is_available: bool}>>
     */
    public function getBookableSlotsForRange(Ad $ad, string $from, string $to): array
    {
        $meta = $this->getAvailabilityMetadata($ad);
        $slots = [];
        $current = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($current->lte($end)) {
            $dateStr = $current->toDateString();
            $daySlots = $ad->getBookableSlots($dateStr, $meta['slot_duration'], $meta['buffer_minutes']);

            if (!empty($daySlots)) {
                $slots[$dateStr] = $daySlots;
            }

            $current->addDay();
        }

        return $slots;
    }

    /**
     * Return the slot duration (in minutes) configured for this ad.
     */
    public function getSlotDuration(Ad $ad): int
    {
        return $this->getAvailabilityMetadata($ad)['slot_duration'];
    }

    // -------------------------------------------------------------------------
    // Private helpers
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
        $recurrence = $data['recurrence'] ?? 'once';

        match ($recurrence) {
            'daily' => $builder->daily(),
            'weekly' => $builder->weekly($data['recurrence_days'] ?? []),
            'biweekly' => $builder->biweekly($data['recurrence_days'] ?? []),
            'monthly' => $builder->monthly(['days_of_month' => $data['days_of_month'] ?? []]),
            default => null,
        };
    }

    /** @return array{slot_duration: int, buffer_minutes: int} */
    private function getAvailabilityMetadata(Ad $ad): array
    {
        /** @var Schedule|null $latestSchedule */
        $latestSchedule = $ad->availabilitySchedules()->latest()->first();

        return [
            'slot_duration' => (int) ($latestSchedule?->metadata['slot_duration'] ?? 30),
            'buffer_minutes' => (int) ($latestSchedule?->metadata['buffer_minutes'] ?? 0),
        ];
    }
}

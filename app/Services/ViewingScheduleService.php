<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\Zap\Schedule;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use Carbon\Carbon;
use Zap\Data\WeeklyFrequencyConfig\AbstractWeeklyFrequencyConfig;
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
        $data = $this->applyRecurrenceDayDefaults($data);

        $builder = Zap::for($ad)
            ->named($data['name'])
            ->availability()
            ->noOverlap()
            ->withMetadata([
                'slot_duration' => $data['slot_duration'],
                'buffer_minutes' => $data['buffer_minutes'],
            ]);

        // Apply date range BEFORE addPeriod so that the period's `date` column
        // is set from start_date rather than falling back to now().
        $this->applyDateRange($builder, $data);

        foreach ($data['periods'] as $period) {
            $builder->addPeriod($period['starts_at'], $period['ends_at']);
        }

        $this->applyRecurrence($builder, $data);

        /** @var Schedule */
        return $builder->save();
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
                'starts_at' => Carbon::parse($p->start_time)->format('H:i'),
                'ends_at' => Carbon::parse($p->end_time)->format('H:i'),
            ])->toArray(),
            'recurrence' => 'once',
            'recurrence_days' => null,
            'days_of_month' => null,
            'slot_duration' => $schedule->metadata['slot_duration'] ?? 30,
            'buffer_minutes' => $schedule->metadata['buffer_minutes'] ?? 0,
        ], $this->extractRecurrencePayloadFromSchedule($schedule), $data);

        return $this->createAvailability($ad, $merged);
    }

    /**
     * Reserve a bookable slot by creating an appointment schedule (exclusive).
     *
     * @param  array{date: string, starts_at: string, ends_at: string, metadata: array<string, mixed>}  $data
     */
    public function reserveSlot(Ad $ad, array $data): Schedule
    {
        /** @var Schedule */
        return Zap::for($ad)
            ->named('Visite provisoire — '.$data['date'])
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
     * Return bookable slots grouped by date for a date range (calendar view).
     *
     * @return array<string, list<array{start_time: string, end_time: string}>>
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

    /**
     * Zap filtre les plannings hebdo avec whereJsonContains('frequency_config->days', $weekday).
     * Si aucun jour n’est envoyé, on utilise le jour calendaire de starts_on (ex. samedi 21 → "saturday").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyRecurrenceDayDefaults(array $data): array
    {
        $recurrence = $data['recurrence'] ?? 'once';
        if (!in_array($recurrence, ['weekly', 'biweekly'], true)) {
            return $data;
        }

        $days = $data['recurrence_days'] ?? null;
        if (!is_array($days)) {
            $days = [];
        }

        $allowed = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $normalized = array_values(array_filter(
            array_map(static fn (mixed $d): string => is_string($d) ? strtolower($d) : '', $days),
            static fn (string $d): bool => in_array($d, $allowed, true),
        ));

        if ($normalized === []) {
            $data['recurrence_days'] = [self::weekdayKeyFromDateString((string) $data['starts_on'])];
        } else {
            $data['recurrence_days'] = $normalized;
        }

        return $data;
    }

    private static function weekdayKeyFromDateString(string $startsOn): string
    {
        $d = Carbon::parse($startsOn)->startOfDay();

        return match ($d->dayOfWeek) {
            Carbon::MONDAY => 'monday',
            Carbon::TUESDAY => 'tuesday',
            Carbon::WEDNESDAY => 'wednesday',
            Carbon::THURSDAY => 'thursday',
            Carbon::FRIDAY => 'friday',
            Carbon::SATURDAY => 'saturday',
            Carbon::SUNDAY => 'sunday',
            default => 'monday',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRecurrencePayloadFromSchedule(Schedule $schedule): array
    {
        if (!$schedule->is_recurring) {
            return [];
        }

        $frequency = $schedule->frequency;
        $value = $frequency instanceof \BackedEnum
            ? (string) $frequency->value
            : (string) $frequency;

        $days = $this->weekDaysFromFrequencyConfig($schedule->frequency_config);
        $fallbackDay = self::weekdayKeyFromDateString($schedule->start_date->toDateString());

        return match ($value) {
            'daily' => ['recurrence' => 'daily'],
            'weekly', 'weekly_odd', 'weekly_even' => [
                'recurrence' => 'weekly',
                'recurrence_days' => $days !== [] ? $days : [$fallbackDay],
            ],
            'biweekly' => [
                'recurrence' => 'biweekly',
                'recurrence_days' => $days !== [] ? $days : [$fallbackDay],
            ],
            'monthly', 'bimonthly', 'quarterly', 'semiannually', 'annually' => [
                'recurrence' => 'monthly',
                'days_of_month' => $this->daysOfMonthFromFrequencyConfig($schedule->frequency_config) ?? [Carbon::parse($schedule->start_date)->day],
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function weekDaysFromFrequencyConfig(mixed $config): array
    {
        $allowed = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        if ($config instanceof AbstractWeeklyFrequencyConfig) {
            return array_values(array_filter(array_map(
                static fn (mixed $d): string => is_string($d) ? strtolower($d) : '',
                $config->days,
            ), static fn (string $d): bool => in_array($d, $allowed, true)));
        }

        if (is_array($config) && isset($config['days']) && is_array($config['days'])) {
            return array_values(array_filter(array_map(
                static fn (mixed $d): string => is_string($d) ? strtolower($d) : '',
                $config['days'],
            ), static fn (string $d): bool => in_array($d, $allowed, true)));
        }

        return [];
    }

    /**
     * @return list<int>|null
     */
    private function daysOfMonthFromFrequencyConfig(mixed $config): ?array
    {
        if (is_array($config) && isset($config['days_of_month']) && is_array($config['days_of_month'])) {
            /** @var list<int> $out */
            $out = array_map(static fn (mixed $v): int => (int) $v, $config['days_of_month']);

            return $out;
        }

        return null;
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

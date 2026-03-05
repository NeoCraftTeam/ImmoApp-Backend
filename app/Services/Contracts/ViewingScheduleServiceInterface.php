<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Ad;
use App\Models\Zap\Schedule;

interface ViewingScheduleServiceInterface
{
    /**
     * Create a one-off or recurring availability schedule.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAvailability(Ad $ad, array $data): Schedule;

    /**
     * Update an existing availability schedule.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAvailability(Ad $ad, Schedule $schedule, array $data): Schedule;

    /**
     * Reserve a bookable slot by creating an appointment schedule (exclusive).
     *
     * @param  array<string, mixed>  $data
     */
    public function reserveSlot(Ad $ad, array $data): Schedule;

    /**
     * Release a reserved slot by deleting its appointment schedule.
     */
    public function releaseSlot(Schedule $appointmentSchedule): void;

    /**
     * Return bookable slots for a given date.
     *
     * @return list<array{starts_at: string, ends_at: string}>
     */
    public function getBookableSlotsForDate(Ad $ad, string $date): array;

    /**
     * Return bookable slots grouped by date for a date range.
     *
     * @return array<string, list<array{start_time: string, end_time: string}>>
     */
    public function getBookableSlotsForRange(Ad $ad, string $from, string $to): array;

    /**
     * Return the slot duration (in minutes) for this ad.
     */
    public function getSlotDuration(Ad $ad): int;
}

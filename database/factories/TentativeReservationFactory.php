<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<TentativeReservation>
 */
class TentativeReservationFactory extends Factory
{
    protected $model = TentativeReservation::class;

    public function definition(): array
    {
        $scheduleId = fake()->uuid();

        return [
            'ad_id' => Ad::factory(),
            'client_id' => User::factory(),
            'appointment_schedule_id' => function (array $attributes) use ($scheduleId): string {
                DB::table('schedules')->insert([
                    'id' => $scheduleId,
                    'schedulable_type' => Ad::class,
                    'schedulable_id' => $attributes['ad_id'],
                    'name' => 'Test availability slot',
                    'start_date' => now()->toDateString(),
                    'is_active' => true,
                    'is_recurring' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $scheduleId;
            },
            'slot_date' => now()->addDay()->toDateString(),
            'slot_starts_at' => '10:00:00',
            'slot_ends_at' => '10:30:00',
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => ReservationStatus::Pending]);
    }

    public function confirmed(): self
    {
        return $this->state(['status' => ReservationStatus::Confirmed]);
    }

    public function cancelled(): self
    {
        return $this->state([
            'status' => ReservationStatus::Cancelled,
            'cancelled_by' => CancelledBy::Client,
        ]);
    }

    public function cancelledByLandlord(): self
    {
        return $this->state([
            'status' => ReservationStatus::Cancelled,
            'cancelled_by' => CancelledBy::Landlord,
        ]);
    }

    public function expired(): self
    {
        return $this->state([
            'status' => ReservationStatus::Expired,
            'cancelled_by' => CancelledBy::System,
        ]);
    }

    /**
     * Pending reservation whose TTL has elapsed (for expiry job tests).
     */
    public function stale(): self
    {
        return $this->state([
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->subHour(),
        ]);
    }
}

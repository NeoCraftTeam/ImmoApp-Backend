<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Models\Zap\Schedule;
use Database\Factories\TentativeReservationFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ad_id
 * @property string $client_id
 * @property string $appointment_schedule_id
 * @property \Illuminate\Support\Carbon $slot_date
 * @property string $slot_starts_at
 * @property string $slot_ends_at
 * @property ReservationStatus $status
 * @property string|null $client_message
 * @property string|null $landlord_notes
 * @property CancelledBy|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon $expires_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ad $ad
 * @property-read User $client
 * @property-read Schedule $appointmentSchedule
 *
 * @method static TentativeReservationFactory factory($count = null, $state = [])
 * @method static Builder<static>|TentativeReservation newModelQuery()
 * @method static Builder<static>|TentativeReservation newQuery()
 * @method static Builder<static>|TentativeReservation onlyTrashed()
 * @method static Builder<static>|TentativeReservation query()
 * @method static Builder<static>|TentativeReservation pending()
 * @method static Builder<static>|TentativeReservation active()
 * @method static Builder<static>|TentativeReservation withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TentativeReservation withoutTrashed()
 *
 * @mixin Eloquent
 */
class TentativeReservation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tentative_reservations';

    protected $fillable = [
        'ad_id',
        'client_id',
        'appointment_schedule_id',
        'slot_date',
        'slot_starts_at',
        'slot_ends_at',
        'status',
        'client_message',
        'landlord_notes',
        'cancelled_by',
        'cancellation_reason',
        'expires_at',
        'notified_at',
    ];

    protected $hidden = ['deleted_at'];

    #[\Override]
    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
            'status' => ReservationStatus::class,
            'cancelled_by' => CancelledBy::class,
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function appointmentSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'appointment_schedule_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Pending);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed]);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function expiredAndPending(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Pending)
            ->where('expires_at', '<', now());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isOwnedByLandlord(User $user): bool
    {
        return $this->ad->user_id === $user->id;
    }

    public function isOwnedByClient(User $user): bool
    {
        return $this->client_id === $user->id;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() && $this->status === ReservationStatus::Pending;
    }
}

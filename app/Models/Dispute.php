<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $reference
 * @property DisputeType $type
 * @property DisputeStatus $status
 * @property string $initiator_id
 * @property string $respondent_id
 * @property string|null $admin_id
 * @property string|null $ad_id
 * @property string|null $lease_id
 * @property string|null $payment_id
 * @property string $title
 * @property string $description
 * @property int|null $amount_claimed
 * @property string|null $resolution_note
 * @property Carbon $sla_deadline
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'reference',
        'type',
        'status',
        'initiator_id',
        'respondent_id',
        'admin_id',
        'ad_id',
        'lease_id',
        'payment_id',
        'title',
        'description',
        'amount_claimed',
        'resolution_note',
        'sla_deadline',
        'resolved_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'type' => DisputeType::class,
            'status' => DisputeStatus::class,
            'amount_claimed' => 'integer',
            'sla_deadline' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<LeaseContract, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'lease_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<DisputeMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('created_at');
    }

    /** @return HasMany<DisputeEvidence, $this> */
    public function evidences(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class)->orderByDesc('created_at');
    }

    /** @param  Builder<Dispute>  $query */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [
            DisputeStatus::OPEN->value,
            DisputeStatus::UNDER_REVIEW->value,
            DisputeStatus::MEDIATION->value,
        ]);
    }

    /** @param  Builder<Dispute>  $query */
    #[Scope]
    protected function involving(Builder $query, string $userId): void
    {
        $query->where(function (Builder $q) use ($userId): void {
            $q->where('initiator_id', $userId)
                ->orWhere('respondent_id', $userId);
        });
    }

    public function isParty(string $userId): bool
    {
        return $this->initiator_id === $userId || $this->respondent_id === $userId;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'admin_id', 'resolution_note', 'resolved_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Litige {$eventName}");
    }
}

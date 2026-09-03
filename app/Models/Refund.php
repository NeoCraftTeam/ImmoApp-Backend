<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property RefundStatus $status
 * @property array<string, mixed>|null $gateway_response
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'payment_id',
        'user_id',
        'processed_by',
        'amount',
        'currency',
        'reason',
        'is_partial',
        'admin_note',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'is_partial' => 'boolean',
            'side_effects_reversed' => 'boolean',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === RefundStatus::Completed;
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatus::Pending;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Remboursement #{$this->id} {$eventName}");
    }
}

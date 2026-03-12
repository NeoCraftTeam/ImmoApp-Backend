<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdReportReason;
use App\Enums\AdReportScamReason;
use App\Enums\AdReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $ad_id
 * @property string $reporter_id
 * @property string|null $owner_id
 * @property AdReportReason $reason
 * @property AdReportScamReason|null $scam_reason
 * @property array<int, string>|null $payment_methods
 * @property string|null $description
 * @property AdReportStatus $status
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $resolved_by
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class AdReport extends Model
{
    /** @use HasFactory<\Database\Factories\AdReportFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'ad_id',
        'reporter_id',
        'owner_id',
        'reason',
        'scam_reason',
        'payment_methods',
        'description',
        'status',
        'admin_notes',
        'resolved_at',
        'resolved_by',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'reason' => AdReportReason::class,
        'scam_reason' => AdReportScamReason::class,
        'payment_methods' => 'array',
        'status' => AdReportStatus::class,
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function open($query): void
    {
        $query->whereIn('status', [AdReportStatus::PENDING, AdReportStatus::REVIEWING]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reason', 'scam_reason', 'status', 'resolved_at', 'resolved_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Signalement d'annonce {$eventName}");
    }
}

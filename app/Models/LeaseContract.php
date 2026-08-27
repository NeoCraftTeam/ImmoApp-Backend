<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaseStatus;
use Database\Factories\LeaseContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $lease_start
 * @property Carbon|null $lease_end
 * @property LeaseStatus $status
 * @property Carbon|null $terminated_at
 * @property string|null $termination_reason
 * @property Carbon|null $archived_at
 */
class LeaseContract extends Model
{
    /** @use HasFactory<LeaseContractFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'lease_contracts';

    protected $fillable = [
        'user_id',
        'ad_id',
        'tenant_id',
        'unit_reference',
        'contract_number',
        'tenant_name',
        'tenant_phone',
        'tenant_email',
        'tenant_id_number',
        'lease_start',
        'lease_end',
        'lease_duration_months',
        'monthly_rent',
        'deposit_amount',
        'special_conditions',
        'pdf_path',
        'status',
        'terminated_at',
        'termination_reason',
        'archived_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<TenantScreeningRequest, $this> */
    public function screeningRequests(): HasMany
    {
        return $this->hasMany(TenantScreeningRequest::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'lease_start' => 'date',
            'lease_end' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'status' => LeaseStatus::class,
            'terminated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Active = status flag is Active AND archive timestamp is null. The
     * date window is enforced by the scheduled expiry sweep flipping
     * passed-end leases to {@see LeaseStatus::Expired}, so we don't
     * double-check `lease_end` here.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LeaseStatus::Active->value)
            ->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Rent that has accrued from {@see $lease_start} up to the effective end of
     * the in-force period, so it can be compared against cumulative expenses in
     * a profit/loss statement (both figures are then all-time totals).
     *
     * Revenue is recognised per whole month elapsed (rent is due monthly). The
     * effective end depends on the lifecycle status:
     * - Active: `$asOf` (still generating rent);
     * - Expired: {@see $lease_end};
     * - Terminated / Archived: {@see $terminated_at} (falling back to lease_end).
     *
     * Drafts carry no obligations yet, and a lease that has not started accrues
     * nothing — both return `0.0`.
     */
    public function accruedRentToDate(?Carbon $asOf = null): float
    {
        if ($this->status === LeaseStatus::Draft || $this->lease_start === null) {
            return 0.0;
        }

        $asOf ??= Carbon::now();

        if ($asOf->lessThan($this->lease_start)) {
            return 0.0;
        }

        $end = match ($this->status) {
            LeaseStatus::Expired => $this->lease_end ?? $asOf,
            LeaseStatus::Terminated, LeaseStatus::Archived => $this->terminated_at ?? $this->lease_end ?? $asOf,
            default => $asOf,
        };

        if ($end->greaterThan($asOf)) {
            $end = $asOf;
        }

        $monthsInForce = max(0, (int) $this->lease_start->diffInMonths($end));

        return (float) $this->monthly_rent * $monthsInForce;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Carbon $expires_at
 * @property Carbon|null $viewed_at
 * @property Carbon|null $signed_at
 * @property Carbon|null $declined_at
 */
class LeaseSignatureRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'lease_contract_id',
        'requested_by',
        'signer_email',
        'signer_name',
        'token',
        'status',
        'viewed_at',
        'signed_at',
        'declined_at',
        'decline_reason',
        'signature_hash',
        'expires_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'declined_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** @return BelongsTo<LeaseContract, $this> */
    public function leaseContract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

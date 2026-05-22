<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaseAuditEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit log for lease contract lifecycle events.
 *
 * Audit Item 1 — tracks generate / view / download / sign events to provide
 * a tamper-evident trail required for legal enforceability (OHADA/CEMAC).
 *
 * @property string $id
 * @property string $lease_contract_id
 * @property string|null $user_id
 * @property LeaseAuditEvent $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string,mixed>|null $metadata
 * @property Carbon $occurred_at
 */
class LeaseSignatureAuditLog extends Model
{
    use HasUuids;

    protected $table = 'lease_signature_audit_logs';

    protected $fillable = [
        'lease_contract_id',
        'user_id',
        'event',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'event' => LeaseAuditEvent::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LeaseContract, $this> */
    public function leaseContract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience factory method — records one audit entry.
     *
     * @param  array<string,mixed>|null  $metadata
     */
    public static function record(
        string $leaseContractId,
        LeaseAuditEvent $event,
        ?string $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null,
    ): self {
        return self::create([
            'lease_contract_id' => $leaseContractId,
            'user_id' => $userId,
            'event' => $event,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }
}

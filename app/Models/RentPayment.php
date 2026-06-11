<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RentPaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rent-collection ledger row.
 *
 * One row per actual rent received by the landlord (cash, mobile money,
 * bank transfer, other). Distinct from {@see Payment} which handles
 * platform fees (credits / subscriptions / unlocks / boosts) flowing
 * through Stripe or GeniusPay. Rent in CEMAC is mostly out-of-band, so
 * the landlord maintains this ledger manually — partial payments for the
 * same month are captured as multiple rows.
 *
 * @property Carbon|null $period_month
 * @property Carbon|null $received_at
 */
final class RentPayment extends Model
{
    /** @use HasFactory<RentPaymentFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'rent_payments';

    protected $fillable = [
        'lease_contract_id',
        'period_month',
        'amount',
        'payment_method',
        'received_at',
        'notes',
        'recorded_by_user_id',
    ];

    /** @return BelongsTo<LeaseContract, $this> */
    public function leaseContract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'received_at' => 'date',
            'amount' => 'integer',
        ];
    }
}

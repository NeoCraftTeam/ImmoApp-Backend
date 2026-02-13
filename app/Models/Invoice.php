<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $invoice_number
 * @property string $subscription_id
 * @property string $agency_id
 * @property string|null $payment_id
 * @property string $plan_name
 * @property string $billing_period
 * @property int $amount
 * @property string $currency
 * @property \Illuminate\Support\Carbon $issued_at
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 */
class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_number',
        'subscription_id',
        'agency_id',
        'payment_id',
        'plan_name',
        'billing_period',
        'amount',
        'currency',
        'issued_at',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'issued_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Generate a unique invoice number (KH-YYYYMM-XXXX).
     */
    public static function generateNumber(): string
    {
        $prefix = 'KH-'.now()->format('Ym');
        $lastInvoice = self::where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->first();

        $sequence = 1;
        if ($lastInvoice) {
            $lastSequence = (int) substr($lastInvoice->invoice_number, -4);
            $sequence = $lastSequence + 1;
        }

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 0, ',', ' ').' '.$this->currency;
    }
}

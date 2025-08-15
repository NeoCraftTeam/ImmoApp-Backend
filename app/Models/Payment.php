<?php

namespace App\Models;

use App\PaymentMethod;
use App\PaymentStatus;
use App\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment';

    protected $fillable = [

        'type',
        'amount',
        'transaction_id',
        'payment_method',
        'user_id',
        'status'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'type' => PaymentType::class,
        'payment_method' => PaymentMethod::class,
        'status' => PaymentStatus::class
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns true if the payment method is Orange Money.
     *
     */
    public function isOrangeMoney(): bool
    {
        return $this->payment_method === PaymentMethod::ORANGE_MONEY;
    }

    /**
     * Returns true if the payment method is MTN Mobile Money.
     *
     */
    public function isMobileMoney(): bool
    {
        return $this->payment_method === PaymentMethod::MOBILE_MONEY;
    }

    /**
     * Returns true if the payment method is Stripe.
     *
     */
    public function isStripe(): bool
    {
        return $this->payment_method === PaymentMethod::STRIPE;
    }

    /**
     * Returns true if the payment  is pending.
     *
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Returns true if the payment  is successful.
     *
     */
    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::SUCCESS;
    }

    /**
     * Returns true if the payment has failed.
     *
     */
    public function hasFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    /**
     * Returns true if the payment is for unlocking an ad.
     *
     */
    public function isUnlocked(): bool
    {
        return $this->type === PaymentType::UNLOCK;
    }

    /**
     * Returns true if the payment is for a subscription.
     *
     */
    public function isSubscribed(): bool
    {
        return $this->type === PaymentType::SUBSCRIPTION;
    }

    /**
     * Returns true if the payment is for boosting an ad.
     *
     */
    public function isBoosted(): bool
    {
        return $this->type === PaymentType::BOOST;
    }
}

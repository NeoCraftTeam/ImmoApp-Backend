<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PaymentType $type
 * @property string $amount
 * @property string $transaction_id
 * @property PaymentMethod $payment_method
 * @property int $user_id
 * @property PaymentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 *
 * @method static PaymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Payment newModelQuery()
 * @method static Builder<static>|Payment newQuery()
 * @method static Builder<static>|Payment onlyTrashed()
 * @method static Builder<static>|Payment query()
 * @method static Builder<static>|Payment whereAmount($value)
 * @method static Builder<static>|Payment whereCreatedAt($value)
 * @method static Builder<static>|Payment whereDeletedAt($value)
 * @method static Builder<static>|Payment whereId($value)
 * @method static Builder<static>|Payment wherePaymentMethod($value)
 * @method static Builder<static>|Payment whereStatus($value)
 * @method static Builder<static>|Payment whereTransactionId($value)
 * @method static Builder<static>|Payment whereType($value)
 * @method static Builder<static>|Payment whereUpdatedAt($value)
 * @method static Builder<static>|Payment whereUserId($value)
 * @method static Builder<static>|Payment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Payment withoutTrashed()
 *
 * @property int $ad_id
 * @property-read \App\Models\Ad $ad
 *
 * @method static Builder<static>|Payment whereAdId($value)
 *
 * @mixin Eloquent
 */
class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [

        'type',
        'amount',
        'transaction_id',
        'payment_method',
        'user_id',
        'ad_id',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'type' => PaymentType::class,
        'payment_method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::SUCCESS;
    }

    /**
     * Returns true if the payment method is Orange Money.
     */
    public function isOrangeMoney(): bool
    {
        return $this->payment_method === PaymentMethod::ORANGE_MONEY;
    }

    /**
     * Returns true if the payment method is MTN Mobile Money.
     */
    public function isMobileMoney(): bool
    {
        return $this->payment_method === PaymentMethod::MOBILE_MONEY;
    }

    /**
     * Returns true if the payment method is Stripe.
     */
    public function isStripe(): bool
    {
        return $this->payment_method === PaymentMethod::STRIPE;
    }

    /**
     * Returns true if the payment  is pending.
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Returns true if the payment  is successful.
     */
    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::SUCCESS;
    }

    /**
     * Returns true if the payment has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    /**
     * Returns true if the payment is for unlocking an ad.
     */
    public function isUnlocked(): bool
    {
        return $this->type === PaymentType::UNLOCK;
    }

    /**
     * Returns true if the payment is for a subscription.
     */
    public function isSubscribed(): bool
    {
        return $this->type === PaymentType::SUBSCRIPTION;
    }

    /**
     * Returns true if the payment is for boosting an ad.
     */
    public function isBoosted(): bool
    {
        return $this->type === PaymentType::BOOST;
    }
}

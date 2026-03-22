<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $promo_code_id
 * @property string $user_id
 * @property string|null $payment_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PromoCodeUsage extends Model
{
    use HasUuids;

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'payment_id',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string|null $description
 * @property string $discount_type
 * @property float $discount_value
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property string $applicable_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PromoCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
        'applicable_to',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function isValidForUser(User $user, string $paymentType): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->applicable_to !== 'all' && $this->applicable_to !== $paymentType) {
            return false;
        }

        if ($this->usages()->where('user_id', $user->id)->exists()) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $originalAmount): float
    {
        if ($this->discount_type === 'percentage') {
            return round($originalAmount * ($this->discount_value / 100), 2);
        }

        return min($this->discount_value, $originalAmount);
    }
}

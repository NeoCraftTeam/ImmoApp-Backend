<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $email
 * @property string|null $name
 * @property string $locale
 * @property string $source
 * @property string $token
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NewsletterSubscriber extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'name',
        'locale',
        'source',
        'token',
        'confirmed_at',
        'unsubscribed_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function isSubscribed(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    #[\Override]
    protected static function booted(): void
    {
        self::creating(function (self $subscriber): void {
            if (empty($subscriber->token)) {
                $subscriber->token = hash('sha256', Str::random(64));
            }
        });
    }
}

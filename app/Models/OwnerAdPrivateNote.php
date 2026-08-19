<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OwnerAdPrivateNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'ad_id', 'user_id', 'is_property_owner', 'owner_name', 'owner_address',
        'owner_phone', 'owner_email', 'notes',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_property_owner' => 'boolean',
            'owner_name' => 'encrypted',
            'owner_address' => 'encrypted',
            'owner_phone' => 'encrypted',
            'owner_email' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'id_number',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaseContracts(): HasMany
    {
        return $this->hasMany(LeaseContract::class);
    }
}

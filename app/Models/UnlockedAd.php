<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnlockedAd extends Model
{
    use HasFactory, softDeletes;

    public $timestamps = false;

    protected $fillable = [
        'ad_id',
        'user_id',
        'payment_id',
    ];

    protected $hidden = [
        'unlocked_at',
        'updated_at',
        'deleted_at'
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DisputeMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $dispute_id
 * @property string $sender_id
 * @property string $body
 * @property bool $is_internal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DisputeMessage extends Model
{
    /** @use HasFactory<DisputeMessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'dispute_id',
        'sender_id',
        'body',
        'is_internal',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /** @return BelongsTo<Dispute, $this> */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}

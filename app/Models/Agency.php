<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use hasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $table = 'agency';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'owner_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    public function owner(): belongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function users(): hasMany
    {
        return $this->hasMany(User::class);
    }
}

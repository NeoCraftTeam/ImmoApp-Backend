<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdImage extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'image_path',
        'ad_id',
    ];


    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}

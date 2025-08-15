<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quarter extends Model
{
    use HasFactory, softDeletes;

    protected $table = 'quarter';

    protected $fillable = [
        'name',
        'city_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

 }

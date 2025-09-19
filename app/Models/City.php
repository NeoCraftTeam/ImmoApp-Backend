<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static CityFactory factory($count = null, $state = [])
 * @method static Builder<static>|City newModelQuery()
 * @method static Builder<static>|City newQuery()
 * @method static Builder<static>|City onlyTrashed()
 * @method static Builder<static>|City query()
 * @method static Builder<static>|City whereCreatedAt($value)
 * @method static Builder<static>|City whereDeletedAt($value)
 * @method static Builder<static>|City whereId($value)
 * @method static Builder<static>|City whereName($value)
 * @method static Builder<static>|City whereUpdatedAt($value)
 * @method static Builder<static>|City withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|City withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quarter> $quarters
 * @property-read int|null $quarters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @mixin Eloquent
 */
class City extends Model
{
    use HasFactory, softDeletes;

    protected $table = 'city';

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];


    /**
     * Mutator to always set the name attribute
     * with the first letter in uppercase and the rest in lowercase.
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = ucfirst(strtolower($value));
    }

     /**
     * Get all of the quarters for the City
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function quarters(): hasMany
    {
        return $this->hasMany(Quarter::class);
    }

    public function users(): hasMany
    {
        return $this->hasMany(User::class);
    }
}

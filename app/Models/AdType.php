<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdTypeFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string|null $desc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static AdTypeFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdType newModelQuery()
 * @method static Builder<static>|AdType newQuery()
 * @method static Builder<static>|AdType onlyTrashed()
 * @method static Builder<static>|AdType query()
 * @method static Builder<static>|AdType whereCreatedAt($value)
 * @method static Builder<static>|AdType whereDeletedAt($value)
 * @method static Builder<static>|AdType whereDesc($value)
 * @method static Builder<static>|AdType whereId($value)
 * @method static Builder<static>|AdType whereName($value)
 * @method static Builder<static>|AdType whereUpdatedAt($value)
 * @method static Builder<static>|AdType withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AdType withoutTrashed()
 *
 * @mixin Eloquent
 */
class AdType extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'ad_type';

    protected $fillable = [
        'name',
        'desc',
    ];

    protected function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'desc'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Type d'annonce « {$this->name} » {$eventName}");
    }

    /**
     * Pick the best matching ad type when several rows match a short hint (e.g. LLM returns "Appartement"
     * but the catalogue has "appartement simple" vs "appartement meublé").
     *
     * @param  ?bool  $furnishedPreference  null = deduce from query text only; true/false = explicit signal
     */
    public static function resolveFromNaturalSearchHint(?string $typeNameHint, string $originalQuery, ?bool $furnishedPreference = null): ?self
    {
        $hint = $typeNameHint !== null ? trim($typeNameHint) : '';
        if ($hint === '') {
            return null;
        }

        $wantsFurnished = (bool) preg_match('/meublée?|meuble\b/ui', $originalQuery)
            || $furnishedPreference === true;
        $wantsUnfurnished = !$wantsFurnished
            && (
                $furnishedPreference === false
                || (bool) preg_match('/\bsimple\b|non\s+meublé|sans\s+meubl/i', $originalQuery)
            );

        $like = '%'.addcslashes($hint, '%_\\').'%';

        /** @var Collection<int, self> $candidates */
        $candidates = static::query()->where('name', 'ilike', $like)->orderBy('name')->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $hasMeuble = static fn (self $t): bool => (bool) preg_match('/meubl/i', (string) $t->name);

        if ($wantsFurnished) {
            $preferred = $candidates->filter($hasMeuble);
            if ($preferred->isNotEmpty()) {
                return $preferred->sortByDesc(fn (self $t): int => mb_strlen((string) $t->name))->first();
            }
        }

        if ($wantsUnfurnished) {
            $preferred = $candidates->reject($hasMeuble);
            if ($preferred->isNotEmpty()) {
                return $preferred->sortByDesc(fn (self $t): int => mb_strlen((string) $t->name))->first();
            }
        }

        $exact = $candidates->first(static fn (self $t): bool => mb_strtolower((string) $t->name) === mb_strtolower($hint));
        if ($exact instanceof self) {
            return $exact;
        }

        if (!$wantsFurnished) {
            $preferred = $candidates->reject($hasMeuble);
            if ($preferred->isNotEmpty()) {
                return $preferred->first();
            }
        }

        return $candidates->first();
    }
}

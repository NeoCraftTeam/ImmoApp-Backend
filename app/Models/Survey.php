<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SurveyFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SurveyQuestion> $questions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SurveyResponse> $responses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnonymousSurveyResponse> $anonymousResponses
 *
 * @method static SurveyFactory factory($count = null, $state = [])
 * @method static Builder<static>|Survey active()
 * @method static Builder<static>|Survey publiclyVisible()
 * @method static Builder<static>|Survey newModelQuery()
 * @method static Builder<static>|Survey newQuery()
 * @method static Builder<static>|Survey query()
 *
 * @mixin Eloquent
 */
class Survey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_active',
        'is_public',
    ];

    #[\Override]
    protected static function booted(): void
    {
        static::creating(function (self $survey): void {
            if (blank($survey->slug)) {
                $survey->slug = static::uniqueSlug($survey->title);
            }
        });

        static::updating(function (self $survey): void {
            if ($survey->isDirty('title') && !$survey->isDirty('slug')) {
                $survey->slug = static::uniqueSlug($survey->title, $survey->id);
            }
        });
    }

    public static function uniqueSlug(string $title, ?string $excludeId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    #[\Override]
    public function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function anonymousResponses(): HasMany
    {
        return $this->hasMany(AnonymousSurveyResponse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true);
    }
}

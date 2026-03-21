<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SurveyAnonymousAudience;
use Database\Factories\AnonymousSurveyResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $survey_id
 * @property string $session_token_hash
 * @property string|null $ip_hash
 * @property Carbon $submitted_at
 * @property Carbon|null $viewed_at
 * @property SurveyAnonymousAudience $respondent_audience
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Survey $survey
 * @property-read Collection<int, AnonymousSurveyAnswer> $answers
 *
 * @method static AnonymousSurveyResponseFactory factory($count = null, $state = [])
 */
class AnonymousSurveyResponse extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'survey_id',
        'session_token_hash',
        'ip_hash',
        'submitted_at',
        'viewed_at',
    ];

    #[\Override]
    public function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'viewed_at' => 'datetime',
            'respondent_audience' => SurveyAnonymousAudience::class,
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AnonymousSurveyAnswer::class, 'anonymous_response_id');
    }
}

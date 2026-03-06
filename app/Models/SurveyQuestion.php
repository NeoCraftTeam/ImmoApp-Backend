<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SurveyQuestionFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $survey_id
 * @property string $text
 * @property string $type
 * @property array<int, string>|null $options
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Survey $survey
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SurveyResponse> $responses
 *
 * @method static SurveyQuestionFactory factory($count = null, $state = [])
 * @method static Builder<static>|SurveyQuestion newModelQuery()
 * @method static Builder<static>|SurveyQuestion newQuery()
 * @method static Builder<static>|SurveyQuestion query()
 *
 * @mixin Eloquent
 */
class SurveyQuestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'survey_id',
        'text',
        'type',
        'options',
        'order',
    ];

    #[\Override]
    public function casts(): array
    {
        return [
            'options' => 'array',
            'order' => 'integer',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'survey_question_id');
    }
}

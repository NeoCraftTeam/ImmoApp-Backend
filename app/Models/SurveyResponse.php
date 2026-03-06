<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SurveyResponseFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $survey_id
 * @property string $survey_question_id
 * @property string $user_id
 * @property string $answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Survey $survey
 * @property-read SurveyQuestion $question
 * @property-read User $user
 *
 * @method static SurveyResponseFactory factory($count = null, $state = [])
 * @method static Builder<static>|SurveyResponse newModelQuery()
 * @method static Builder<static>|SurveyResponse newQuery()
 * @method static Builder<static>|SurveyResponse query()
 *
 * @mixin Eloquent
 */
class SurveyResponse extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'survey_id',
        'survey_question_id',
        'user_id',
        'answer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

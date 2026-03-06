<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AnonymousSurveyAnswerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $anonymous_response_id
 * @property string $survey_question_id
 * @property string $answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AnonymousSurveyResponse $response
 * @property-read SurveyQuestion $question
 *
 * @method static AnonymousSurveyAnswerFactory factory($count = null, $state = [])
 */
class AnonymousSurveyAnswer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'anonymous_response_id',
        'survey_question_id',
        'answer',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(AnonymousSurveyResponse::class, 'anonymous_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}

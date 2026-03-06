<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnonymousSurveyAnswer;
use App\Models\AnonymousSurveyResponse;
use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnonymousSurveyAnswer>
 */
class AnonymousSurveyAnswerFactory extends Factory
{
    protected $model = AnonymousSurveyAnswer::class;

    public function definition(): array
    {
        return [
            'anonymous_response_id' => AnonymousSurveyResponse::factory(),
            'survey_question_id' => SurveyQuestion::factory(),
            'answer' => fake()->sentence(),
        ];
    }
}

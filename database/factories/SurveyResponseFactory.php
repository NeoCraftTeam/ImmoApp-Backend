<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    public function definition(): array
    {
        $survey = Survey::factory()->create();
        $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

        return [
            'survey_id' => $survey->id,
            'survey_question_id' => $question->id,
            'user_id' => User::factory(),
            'answer' => $this->faker->sentence(),
        ];
    }
}

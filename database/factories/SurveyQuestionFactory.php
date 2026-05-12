<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyQuestion>
 */
class SurveyQuestionFactory extends Factory
{
    protected $model = SurveyQuestion::class;

    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'text' => $this->faker->sentence().' ?',
            'type' => $this->faker->randomElement(['multiple_choice', 'checkbox', 'rating', 'text']),
            'options' => null,
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }
}

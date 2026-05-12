<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnonymousSurveyResponse;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnonymousSurveyResponse>
 */
class AnonymousSurveyResponseFactory extends Factory
{
    protected $model = AnonymousSurveyResponse::class;

    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'session_token_hash' => hash('sha256', fake()->uuid()),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'submitted_at' => now(),
        ];
    }
}

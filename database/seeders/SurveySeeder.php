<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Database\Seeder;

final class SurveySeeder extends Seeder
{
    public function run(): void
    {
        $survey = Survey::firstOrCreate(
            ['title' => 'Votre expérience sur KeyHome'],
            [
                'description' => 'Aidez-nous à améliorer votre expérience en répondant à quelques questions rapides.',
                'is_active' => true,
            ]
        );

        if ($survey->questions()->count() > 0) {
            return;
        }

        $questions = [
            [
                'text' => 'Comment évaluez-vous votre expérience globale sur KeyHome ?',
                'type' => 'rating',
                'options' => null,
                'order' => 1,
            ],
            [
                'text' => 'Quel type de bien recherchez-vous en priorité ?',
                'type' => 'multiple_choice',
                'options' => ['Appartement', 'Maison', 'Studio', 'Villa', 'Terrain'],
                'order' => 2,
            ],
            [
                'text' => 'Quelles fonctionnalités utilisez-vous le plus ?',
                'type' => 'checkbox',
                'options' => ['Recherche d\'annonces', 'Réservation de visites', 'Messagerie', 'Favoris', 'Alertes'],
                'order' => 3,
            ],
            [
                'text' => 'Avez-vous des suggestions pour améliorer KeyHome ?',
                'type' => 'text',
                'options' => null,
                'order' => 4,
            ],
        ];

        foreach ($questions as $question) {
            SurveyQuestion::create([
                'survey_id' => $survey->id,
                ...$question,
            ]);
        }
    }
}

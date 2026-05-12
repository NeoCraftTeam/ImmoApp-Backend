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
        $title = 'Votre expérience sur KeyHome';

        $survey = Survey::firstOrCreate(
            ['title' => $title],
            [
                'slug' => Survey::uniqueSlug($title),
                'description' => 'Aidez-nous à améliorer votre expérience en répondant à quelques questions rapides.',
                'is_active' => true,
                'is_public' => true,
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
                'text' => 'Quel est votre profil sur KeyHome ?',
                'type' => 'multiple_choice',
                'options' => ['Je cherche un logement', 'Je suis propriétaire / bailleur', 'Je suis agent immobilier', 'Je navigue par curiosité'],
                'order' => 2,
            ],
            [
                'text' => 'Dans quelle ville ou région recherchez-vous un bien ?',
                'type' => 'text', // libre, pas de liste figée
                'options' => null,
                'order' => 3,
            ],
            [
                'text' => 'Quel type de bien recherchez-vous en priorité ?',
                'type' => 'multiple_choice',
                'options' => ['Appartement', 'Maison', 'Studio', 'Villa', 'Terrain', 'Local commercial'],
                'order' => 4,
            ],
            [
                'text' => 'Qu\'est-ce qui vous a le plus manqué lors de votre utilisation ?',
                'type' => 'checkbox',
                'options' => [
                    'Plus de photos / visite virtuelle',
                    'Des prix plus transparents',
                    'Un contact plus facile avec le propriétaire',
                    'Plus d\'annonces disponibles',
                    'Une carte interactive',
                    'Des filtres plus précis',
                ],
                'order' => 5,
            ],
            [
                'text' => 'Avez-vous déjà effectué une réservation de visite via KeyHome ?',
                'type' => 'multiple_choice',
                'options' => ['Oui', 'Non, je ne savais pas que c\'était possible', 'Non, je n\'en ai pas eu besoin'],
                'order' => 6,
            ],
            [
                'text' => 'Quelle est votre principale crainte lors d\'une location au Cameroun ?',
                'type' => 'checkbox',
                'options' => [
                    'Annonces frauduleuses',
                    'État réel du logement différent des photos',
                    'Manque de transparence sur les prix',
                    'Manque de contrat officiel',
                    'Difficulté à joindre le propriétaire',
                    'Absence de contrat sécurisé',
                ],
                'order' => 7,
            ],
            [
                'text' => 'Recommanderiez-vous KeyHome à un proche ?',
                'type' => 'multiple_choice',
                'options' => ['Certainement', 'Probablement', 'Pas sûr(e)', 'Non'],
                'order' => 8,
            ],
            [
                'text' => 'Avez-vous des suggestions concrètes pour améliorer KeyHome ?',
                'type' => 'text',
                'options' => null,
                'order' => 9,
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

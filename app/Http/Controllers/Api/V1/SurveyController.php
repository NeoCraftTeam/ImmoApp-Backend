<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Resources\SurveyResource;
use App\Mail\SurveyAdminNotificationMail;
use App\Mail\SurveySubmittedMail;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="📋 Sondages", description="Sondages de satisfaction (clients)")
 */
final readonly class SurveyController
{
    /**
     * Retourne le sondage actif le plus récent (pour affichage du prompt).
     */
    public function active(): JsonResponse|SurveyResource
    {
        $survey = Survey::active()->latest()->first();

        if (!$survey) {
            return response()->json(['message' => 'Aucun sondage actif.'], 404);
        }

        return new SurveyResource($survey);
    }

    /**
     * Affiche un sondage actif avec ses questions.
     */
    public function show(Survey $survey): JsonResponse|SurveyResource
    {
        if (!$survey->is_active) {
            return response()->json(['message' => 'Sondage inactif.'], 404);
        }

        $survey->load('questions');

        return new SurveyResource($survey);
    }

    /**
     * Soumet les réponses à un sondage (une réponse par question par utilisateur).
     */
    public function submitResponse(Request $request, Survey $survey): JsonResponse
    {
        if (!$survey->is_active) {
            return response()->json(['message' => 'Sondage inactif.'], 422);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => [
                'required',
                'uuid',
                Rule::exists('survey_questions', 'id')->where('survey_id', $survey->id),
            ],
            'answers.*.answer' => ['required'],
        ]);

        $user = $request->user();

        foreach ($validated['answers'] as $response) {
            $answerValue = is_array($response['answer'])
                ? json_encode($response['answer'])
                : (string) $response['answer'];

            SurveyResponse::updateOrCreate(
                [
                    'survey_question_id' => $response['question_id'],
                    'user_id' => $user->id,
                ],
                [
                    'survey_id' => $survey->id,
                    'answer' => $answerValue,
                ]
            );
        }

        try {
            $this->dispatchSurveyEmails($survey, $user, $validated['answers']);
        } catch (\Throwable) {
            // Email failures must never block survey submission
        }

        return response()->json(['message' => 'Merci pour votre participation !'], 201);
    }

    /**
     * Checks whether the authenticated user has already answered a given survey.
     */
    public function hasAnswered(Request $request, Survey $survey): JsonResponse
    {
        $hasAnswered = SurveyResponse::where('survey_id', $survey->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        return response()->json(['has_answered' => $hasAnswered]);
    }

    /**
     * Sends survey submission emails to the respondent and all admins.
     *
     * @param  array<int, array{question_id: string, answer: mixed}>  $rawAnswers
     */
    private function dispatchSurveyEmails(Survey $survey, User $user, array $rawAnswers): void
    {
        Mail::to($user->email)->queue(new SurveySubmittedMail($survey, $user));

        $survey->load('questions');

        $formattedAnswers = collect($rawAnswers)->map(function (array $raw) use ($survey): array {
            $question = $survey->questions->firstWhere('id', $raw['question_id']);
            $answer = is_array($raw['answer']) ? implode(', ', $raw['answer']) : (string) $raw['answer'];

            return [
                'question' => $question !== null ? $question->text : $raw['question_id'],
                'answer' => $answer,
            ];
        })->values()->all();

        $admins = User::where('role', UserRole::ADMIN)->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new SurveyAdminNotificationMail($survey, $user, $formattedAnswers));
        }
    }
}

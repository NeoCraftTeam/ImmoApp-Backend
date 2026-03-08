<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Resources\SurveyResource;
use App\Mail\SurveyAdminNotificationMail;
use App\Mail\SurveySubmittedMail;
use App\Models\AnonymousSurveyAnswer;
use App\Models\AnonymousSurveyResponse;
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
     * @OA\Get(
     *     path="/api/v1/surveys/active",
     *     summary="Sondage actif en cours",
     *     description="Retourne le sondage actif le plus récent à afficher à l'utilisateur.",
     *     tags={"📋 Sondages"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Sondage actif trouvé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="is_active", type="boolean")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Aucun sondage actif")
     * )
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
     * @OA\Get(
     *     path="/api/v1/surveys/{survey}",
     *     summary="Afficher un sondage avec ses questions",
     *     description="Retourne un sondage actif avec la liste de ses questions.",
     *     tags={"📋 Sondages"},
     *
     *     @OA\Parameter(
     *         name="survey",
     *         in="path",
     *         required=true,
     *         description="ID (UUID) du sondage",
     *
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Sondage trouvé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="questions", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="text", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="options", type="array", nullable=true, @OA\Items(type="string")),
     *                     @OA\Property(property="order", type="integer")
     *                 ))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Sondage introuvable ou inactif")
     * )
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
     * @OA\Post(
     *     path="/api/v1/surveys/{survey}/responses",
     *     summary="Soumettre des réponses à un sondage",
     *     description="Enregistre les réponses de l'utilisateur authentifié. Une réponse par question par utilisateur (upsert). Limité à 10 soumissions par minute.",
     *     tags={"📋 Sondages"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="survey",
     *         in="path",
     *         required=true,
     *         description="ID (UUID) du sondage",
     *
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"answers"},
     *
     *             @OA\Property(property="answers", type="array", minItems=1, @OA\Items(
     *                 required={"question_id", "answer"},
     *                 @OA\Property(property="question_id", type="string", format="uuid"),
     *                 @OA\Property(property="answer", description="Texte ou tableau selon le type de question")
     *             ))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Réponses enregistrées",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Merci pour votre participation !"))
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Sondage inactif ou données invalides"),
     *     @OA\Response(response=429, description="Trop de soumissions")
     * )
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
            'anonymous' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $submitAnonymously = (bool) ($validated['anonymous'] ?? false);

        if ($submitAnonymously) {
            return $this->submitAsAnonymous($survey, $validated['answers'], $user);
        }

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
     * @OA\Get(
     *     path="/api/v1/surveys/{survey}/has-answered",
     *     summary="Vérifier si l'utilisateur a déjà répondu",
     *     description="Indique si l'utilisateur authentifié a déjà soumis des réponses pour ce sondage.",
     *     tags={"📋 Sondages"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="survey",
     *         in="path",
     *         required=true,
     *         description="ID (UUID) du sondage",
     *
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Statut de participation",
     *
     *         @OA\JsonContent(@OA\Property(property="has_answered", type="boolean", example=false))
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function hasAnswered(Request $request, Survey $survey): JsonResponse
    {
        $hasAnswered = SurveyResponse::where('survey_id', $survey->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        return response()->json(['has_answered' => $hasAnswered]);
    }

    /**
     * Submit responses anonymously for an authenticated user who opts for guest mode.
     *
     * @param  array<int, array{question_id: string, answer: mixed}>  $answers
     */
    private function submitAsAnonymous(Survey $survey, array $answers, User $user): JsonResponse
    {
        $tokenHash = hash_hmac('sha256', $user->id.'-'.now()->timestamp, (string) config('app.key'));
        $ipHash = hash('sha256', request()->ip() ?? 'unknown');

        $response = AnonymousSurveyResponse::create([
            'survey_id' => $survey->id,
            'session_token_hash' => $tokenHash,
            'ip_hash' => $ipHash,
            'submitted_at' => now(),
        ]);

        $now = now();
        $rows = array_map(
            fn (array $a) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'anonymous_response_id' => $response->id,
                'survey_question_id' => $a['question_id'],
                'answer' => is_array($a['answer']) ? json_encode($a['answer']) : (string) $a['answer'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $answers,
        );

        AnonymousSurveyAnswer::insert($rows);

        return response()->json(['message' => 'Merci pour votre participation anonyme !'], 201);
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

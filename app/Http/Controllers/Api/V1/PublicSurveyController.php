<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Resources\SurveyResource;
use App\Models\AnonymousSurveyAnswer;
use App\Models\AnonymousSurveyResponse;
use App\Models\Survey;
use App\Models\User;
use App\Notifications\NewAnonymousSurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="📋 Sondages publics", description="Sondages accessibles sans authentification")
 */
final class PublicSurveyController
{
    /**
     * @OA\Get(
     *     path="/api/v1/public/surveys",
     *     summary="Lister les sondages publics actifs",
     *     description="Retourne la liste de tous les sondages actifs et publics.",
     *     tags={"📋 Sondages publics"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste des sondages",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="questions_count", type="integer")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $surveys = Survey::query()
            ->publiclyVisible()
            ->withCount('questions')
            ->latest()
            ->get();

        return SurveyResource::collection($surveys);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/surveys/{slug}",
     *     summary="Afficher un sondage public par slug",
     *     description="Retourne les détails d'un sondage public avec ses questions. Vérifie si le client a déjà soumis via `client_token`.",
     *     tags={"📋 Sondages publics"},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Slug unique du sondage",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="client_token",
     *         in="query",
     *         required=false,
     *         description="UUID généré côté client pour détecter les doublons",
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
     *             @OA\Property(property="id", type="string", format="uuid"),
     *             @OA\Property(property="slug", type="string"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="is_public", type="boolean"),
     *             @OA\Property(property="already_submitted", type="boolean"),
     *             @OA\Property(property="questions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="text", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="options", type="array", nullable=true, @OA\Items(type="string")),
     *                 @OA\Property(property="order", type="integer")
     *             ))
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Sondage introuvable ou inactif")
     * )
     */
    public function show(Survey $survey, Request $request): JsonResponse
    {
        abort_unless($survey->is_active && $survey->is_public, 404);

        $survey->load(['questions' => fn ($q) => $q->orderBy('order')]);

        $alreadySubmitted = false;
        $clientToken = $request->string('client_token');
        if ($clientToken->isNotEmpty()) {
            $tokenHash = hash_hmac('sha256', (string) $clientToken, (string) config('app.key'));
            $alreadySubmitted = AnonymousSurveyResponse::query()
                ->where('survey_id', $survey->id)
                ->where('session_token_hash', $tokenHash)
                ->exists();
        }

        return response()->json([
            'id' => $survey->id,
            'slug' => $survey->slug,
            'title' => $survey->title,
            'description' => $survey->description,
            'is_active' => $survey->is_active,
            'is_public' => $survey->is_public,
            'already_submitted' => $alreadySubmitted,
            'questions' => $survey->questions->map(fn ($q) => [
                'id' => $q->id,
                'text' => $q->text,
                'type' => $q->type,
                'options' => $q->options,
                'order' => $q->order,
            ])->values(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/public/surveys/{slug}/respond",
     *     summary="Soumettre des réponses anonymes",
     *     description="Soumet des réponses anonymes à un sondage public. La déduplication est assurée par `client_token`. Limité à 10 soumissions par minute.",
     *     tags={"📋 Sondages publics"},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Slug unique du sondage",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"client_token", "answers"},
     *
     *             @OA\Property(property="client_token", type="string", format="uuid", description="UUID généré côté client pour prévenir les doublons"),
     *             @OA\Property(property="answers", type="array", minItems=1, @OA\Items(
     *                 required={"question_id", "answer"},
     *                 @OA\Property(property="question_id", type="string", format="uuid"),
     *                 @OA\Property(property="answer", description="Texte ou tableau de réponses selon le type de question")
     *             ))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Réponses enregistrées",
     *
     *         @OA\JsonContent(@OA\Property(property="submitted", type="boolean", example=true))
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Réponse déjà soumise (doublon détecté)",
     *
     *         @OA\JsonContent(@OA\Property(property="already_submitted", type="boolean", example=true))
     *     ),
     *
     *     @OA\Response(response=404, description="Sondage introuvable ou inactif"),
     *     @OA\Response(response=422, description="Données de validation invalides"),
     *     @OA\Response(response=429, description="Trop de soumissions")
     * )
     */
    public function submit(Survey $survey, Request $request): JsonResponse
    {
        abort_unless($survey->is_active && $survey->is_public, 404);

        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => [
                'required',
                'uuid',
                Rule::exists('survey_questions', 'id')->where('survey_id', $survey->id),
            ],
            'answers.*.answer' => ['required', 'max:1000'],
        ]);

        $ipHash = hash('sha256', $request->ip() ?? 'unknown');
        $tokenHash = hash_hmac('sha256', (string) $validated['client_token'], (string) config('app.key'));

        if (AnonymousSurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('session_token_hash', $tokenHash)
            ->exists()
        ) {
            return response()->json(['already_submitted' => true], 200);
        }

        if (!RateLimiter::attempt('anonymous-survey:'.$ipHash, 5, fn () => null, 3600)) {
            abort(429, 'Trop de soumissions. Veuillez réessayer dans une heure.');
        }

        $response = AnonymousSurveyResponse::create([
            'survey_id' => $survey->id,
            'session_token_hash' => $tokenHash,
            'ip_hash' => $ipHash,
            'submitted_at' => now(),
        ]);

        $now = now();
        $rows = array_map(
            fn (array $a) => [
                'id' => (string) Str::uuid(),
                'anonymous_response_id' => $response->id,
                'survey_question_id' => $a['question_id'],
                'answer' => is_array($a['answer']) ? json_encode($a['answer']) : (string) $a['answer'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $validated['answers'],
        );

        AnonymousSurveyAnswer::insert($rows);

        $surveySnapshot = $survey;
        $responseSnapshot = $response;
        dispatch(static function () use ($surveySnapshot, $responseSnapshot): void {
            $admins = User::query()->where('role', UserRole::ADMIN)->get();
            Notification::send($admins, new NewAnonymousSurveyResponse($surveySnapshot, $responseSnapshot));
        })->afterResponse();

        return response()->json(['submitted' => true], 201);
    }
}

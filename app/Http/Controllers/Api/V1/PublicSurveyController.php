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

final class PublicSurveyController
{
    /** List all active, publicly visible surveys. */
    public function index(): AnonymousResourceCollection
    {
        $surveys = Survey::query()
            ->publiclyVisible()
            ->withCount('questions')
            ->latest()
            ->get();

        return SurveyResource::collection($surveys);
    }

    /** Show a single public survey by slug, with dedup check via client_token. */
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

    /** Submit anonymous responses from an unauthenticated public client. */
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

<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AnonymousSurveyAnswer;
use App\Models\AnonymousSurveyResponse;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class SubmitAnonymousSurveyAction
{
    /**
     * Submit anonymous answers for the given survey.
     *
     * @param  array<int, array{question_id: string, answer: string}>  $answers
     */
    public function execute(Survey $survey, array $answers, Request $request): AnonymousSurveyResponse
    {
        $sessionToken = $this->hashSessionToken($request);
        $ipHash = $this->hashIp($request);

        // Rate-limit: max 5 survey submissions per hour per IP.
        $rateLimitKey = 'anonymous-survey:'.$ipHash;
        RateLimiter::attempt($rateLimitKey, 5, fn () => null, 3600)
            ?: abort(429, 'Trop de soumissions. Veuillez réessayer dans une heure.');

        $response = AnonymousSurveyResponse::create([
            'survey_id' => $survey->id,
            'session_token_hash' => $sessionToken,
            'ip_hash' => $ipHash,
            'submitted_at' => now(),
        ]);

        $now = now();
        $answerRows = array_map(
            fn (array $a) => [
                'id' => (string) Str::uuid(),
                'anonymous_response_id' => $response->id,
                'survey_question_id' => $a['question_id'],
                'answer' => $a['answer'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $answers,
        );

        AnonymousSurveyAnswer::insert($answerRows);

        // Mark this survey as submitted in the session to prevent re-display of the form.
        $request->session()->put("surveyed_{$survey->id}", true);

        return $response;
    }

    /** Returns true when this browser session has already submitted the given survey. */
    public function alreadySubmitted(Survey $survey, Request $request): bool
    {
        if ($request->session()->has("surveyed_{$survey->id}")) {
            return true;
        }

        $tokenHash = $this->hashSessionToken($request);

        return AnonymousSurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('session_token_hash', $tokenHash)
            ->exists();
    }

    private function hashSessionToken(Request $request): string
    {
        return hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));
    }

    private function hashIp(Request $request): string
    {
        return hash('sha256', $request->ip() ?? 'unknown');
    }
}

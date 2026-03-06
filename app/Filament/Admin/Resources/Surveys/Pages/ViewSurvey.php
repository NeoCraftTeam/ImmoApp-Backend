<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Surveys\Pages;

use App\Filament\Admin\Resources\Surveys\SurveyResource;
use App\Models\AnonymousSurveyResponse;
use App\Models\Survey;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewSurvey extends ViewRecord
{
    protected static string $resource = SurveyResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * Build the list of unique respondents with their answers for the slide-over.
     *
     * @return Collection<int, array{user_id: string, user: User|null, display_name: string, email: string, avatar: string|null, answers: array<int, array{question: string, type: string, answer: string, has_answer: bool}>, answer_count: int, submitted_at: string, submitted_at_raw: mixed, is_anonymous: bool}>
     */
    public function getRespondentsWithAnswers(): Collection
    {
        /** @var Survey $survey */
        $survey = $this->record;

        $survey->loadMissing([
            'questions' => fn ($q) => $q->orderBy('order'),
            'responses.user',
            'responses.question',
        ]);

        // Authenticated user responses
        $authenticated = $survey->responses
            ->sortByDesc('created_at')
            ->groupBy(fn ($r) => $r->user_id ?? ('anon_'.$r->id))
            ->map(function (Collection $responses) use ($survey): array {
                /** @var \App\Models\SurveyResponse $first */
                $first = $responses->first();
                /** @var User|null $user */
                $user = $first->user;

                $answers = $survey->questions->map(function ($question) use ($responses): array {
                    $response = $responses->firstWhere('survey_question_id', $question->id);

                    return [
                        'question' => $question->text,
                        'type' => $question->type,
                        'answer' => $response ? $this->formatAnswer($response->answer, $question->type) : '—',
                        'has_answer' => $response !== null,
                    ];
                })->values()->all();

                $latestAt = $responses->max('created_at');

                return [
                    'user_id' => $first->user_id ?? 'anonymous',
                    'user' => $user,
                    'display_name' => $user ? trim($user->firstname.' '.$user->lastname) : 'Anonyme',
                    'email' => $user !== null ? $user->email : '—',
                    'avatar' => $user?->avatar,
                    'answers' => $answers,
                    'answer_count' => $responses->count(),
                    'submitted_at' => $latestAt instanceof \Carbon\Carbon ? $latestAt->format('d/m/Y à H:i') : '—',
                    'submitted_at_raw' => $latestAt,
                    'is_anonymous' => false,
                ];
            });

        // Anonymous public responses (via shared link)
        $survey->loadMissing([
            'anonymousResponses.answers.question',
        ]);

        $anonymous = $survey->anonymousResponses
            ->sortByDesc('submitted_at')
            ->values()
            ->map(function (AnonymousSurveyResponse $anonResponse, int $index) use ($survey): array {
                $answers = $survey->questions->map(function ($question) use ($anonResponse): array {
                    $answer = $anonResponse->answers->firstWhere('survey_question_id', $question->id);

                    return [
                        'question' => $question->text,
                        'type' => $question->type,
                        'answer' => $answer ? $this->formatAnswer($answer->answer, $question->type) : '—',
                        'has_answer' => $answer !== null,
                    ];
                })->values()->all();

                return [
                    'user_id' => 'anon_public_'.$anonResponse->id,
                    'user' => null,
                    'display_name' => 'Anonyme #'.($index + 1),
                    'email' => '— (lien public)',
                    'avatar' => null,
                    'answers' => $answers,
                    'answer_count' => $anonResponse->answers->count(),
                    'submitted_at' => $anonResponse->submitted_at->format('d/m/Y à H:i'),
                    'submitted_at_raw' => $anonResponse->submitted_at,
                    'is_anonymous' => true,
                ];
            });

        /** @phpstan-ignore return.type */
        return $authenticated->values()
            ->merge($anonymous)
            ->sortByDesc('submitted_at_raw')
            ->values();
    }

    private function formatAnswer(string $answer, string $type): string
    {
        if ($type === 'checkbox' || $type === 'multiple_choice') {
            $decoded = json_decode($answer, true);
            if (is_array($decoded)) {
                return implode(', ', $decoded);
            }
        }

        if ($type === 'rating') {
            $stars = (int) $answer;

            return str_repeat('★', $stars).str_repeat('☆', 5 - $stars).' ('.$answer.'/5)';
        }

        return $answer;
    }
}

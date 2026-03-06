<?php

declare(strict_types=1);

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * @param  array<int, array{text: string, type: string, options?: array<int, string>|null}>  $questions
 */
function makeSurveyWithQuestions(array $questions = [], bool $active = true): Survey
{
    $survey = Survey::factory()->create(['is_active' => $active]);

    foreach ($questions as $index => $q) {
        SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'text' => $q['text'],
            'type' => $q['type'],
            'options' => $q['options'] ?? null,
            'order' => $index + 1,
        ]);
    }

    return $survey->fresh('questions');
}

// ── GET /api/v1/surveys/active ─────────────────────────────────────────────

it('returns 404 when no active survey exists', function (): void {
    $response = $this->getJson('/api/v1/surveys/active');

    $response->assertNotFound();
});

it('returns the active survey', function (): void {
    $survey = Survey::factory()->create(['title' => 'Mon sondage actif']);

    $response = $this->getJson('/api/v1/surveys/active');

    $response->assertOk()
        ->assertJsonPath('data.id', $survey->id)
        ->assertJsonPath('data.title', 'Mon sondage actif')
        ->assertJsonPath('data.is_active', true);
});

it('does not return an inactive survey as active', function (): void {
    Survey::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/surveys/active');

    $response->assertNotFound();
});

// ── GET /api/v1/surveys/{survey} ──────────────────────────────────────────

it('returns a survey with its questions for any user', function (): void {
    $survey = makeSurveyWithQuestions([
        ['text' => 'Question 1 ?', 'type' => 'rating'],
        ['text' => 'Question 2 ?', 'type' => 'multiple_choice', 'options' => ['A', 'B', 'C']],
    ]);

    $response = $this->getJson("/api/v1/surveys/{$survey->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $survey->id)
        ->assertJsonCount(2, 'data.questions');
});

it('returns 404 for an inactive survey', function (): void {
    $survey = Survey::factory()->inactive()->create();

    $response = $this->getJson("/api/v1/surveys/{$survey->id}");

    $response->assertNotFound();
});

it('returns 404 for a non-existent survey uuid', function (): void {
    $response = $this->getJson('/api/v1/surveys/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
});

// ── POST /api/v1/surveys/{survey}/responses ───────────────────────────────

it('requires authentication to submit responses', function (): void {
    $survey = makeSurveyWithQuestions([['text' => 'Q ?', 'type' => 'rating']]);

    $response = $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [
            ['question_id' => $survey->questions->first()->id, 'answer' => '5'],
        ],
    ]);

    $response->assertUnauthorized();
});

it('allows an authenticated user to submit survey responses', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([
        ['text' => 'Note globale ?', 'type' => 'rating'],
        ['text' => 'Votre avis ?', 'type' => 'text'],
    ]);

    $firstQuestion = $survey->questions->first();
    $secondQuestion = $survey->questions->last();

    $response = $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [
            ['question_id' => $firstQuestion->id, 'answer' => '4'],
            ['question_id' => $secondQuestion->id, 'answer' => 'Super application !'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Merci pour votre participation !');

    expect(SurveyResponse::count())->toBe(2);
});

it('stores array answers as JSON for checkbox questions', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([
        ['text' => 'Fonctionnalités ?', 'type' => 'checkbox', 'options' => ['Recherche', 'Messagerie', 'Favoris']],
    ]);

    $question = $survey->questions->first();

    $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [
            ['question_id' => $question->id, 'answer' => ['Recherche', 'Favoris']],
        ],
    ])->assertCreated();

    $storedResponse = SurveyResponse::where('survey_question_id', $question->id)->first();

    expect($storedResponse)->not->toBeNull();
    expect(json_decode((string) $storedResponse->answer, true))->toBe(['Recherche', 'Favoris']);
});

it('updates an existing response when a user submits again (idempotent)', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([
        ['text' => 'Note ?', 'type' => 'rating'],
    ]);

    $question = $survey->questions->first();

    $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [['question_id' => $question->id, 'answer' => '3']],
    ])->assertCreated();

    $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [['question_id' => $question->id, 'answer' => '5']],
    ])->assertCreated();

    expect(SurveyResponse::count())->toBe(1);

    $updated = SurveyResponse::where('survey_question_id', $question->id)->first();
    expect($updated->answer)->toBe('5');
});

it('returns 422 for an inactive survey when submitting responses', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([['text' => 'Q ?', 'type' => 'rating']], active: false);

    $response = $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [
            ['question_id' => $survey->questions->first()->id, 'answer' => '4'],
        ],
    ]);

    $response->assertUnprocessable();
});

it('validates that answers belong to the correct survey', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $surveyA = makeSurveyWithQuestions([['text' => 'Q A ?', 'type' => 'rating']]);
    $surveyB = makeSurveyWithQuestions([['text' => 'Q B ?', 'type' => 'rating']]);

    $questionFromB = $surveyB->questions->first();

    $response = $this->postJson("/api/v1/surveys/{$surveyA->id}/responses", [
        'answers' => [
            ['question_id' => $questionFromB->id, 'answer' => '5'],
        ],
    ]);

    $response->assertUnprocessable();
});

it('requires at least one answer when submitting', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([['text' => 'Q ?', 'type' => 'rating']]);

    $response = $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [],
    ]);

    $response->assertUnprocessable();
});

// ── GET /api/v1/surveys/{survey}/has-answered ──────────────────────────────

it('requires authentication for has-answered', function (): void {
    $survey = Survey::factory()->create();

    $this->getJson("/api/v1/surveys/{$survey->id}/has-answered")
        ->assertUnauthorized();
});

it('returns false when the user has not answered the survey', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = Survey::factory()->create();

    $this->getJson("/api/v1/surveys/{$survey->id}/has-answered")
        ->assertOk()
        ->assertExactJson(['has_answered' => false]);
});

it('returns true when the user has already answered the survey', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([['text' => 'Note ?', 'type' => 'rating']]);
    $question = $survey->questions->first();

    SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'user_id' => $user->id,
        'answer' => '4',
    ]);

    $this->getJson("/api/v1/surveys/{$survey->id}/has-answered")
        ->assertOk()
        ->assertExactJson(['has_answered' => true]);
});

// ── Email dispatch after submit ────────────────────────────────────────────

it('dispatches emails after a successful survey submission', function (): void {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $survey = makeSurveyWithQuestions([['text' => 'Satisfaction ?', 'type' => 'rating']]);
    $question = $survey->questions->first();

    $this->postJson("/api/v1/surveys/{$survey->id}/responses", [
        'answers' => [['question_id' => $question->id, 'answer' => '5']],
    ])->assertCreated();

    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\SurveySubmittedMail::class, fn ($mail) => $mail->hasTo($user->email));
});

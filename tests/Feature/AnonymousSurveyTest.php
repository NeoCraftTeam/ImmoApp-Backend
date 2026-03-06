<?php

declare(strict_types=1);

use App\Models\AnonymousSurveyResponse;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makePublicSurvey(bool $active = true, bool $public = true): Survey
{
    $survey = Survey::factory()->create([
        'is_active' => $active,
        'is_public' => $public,
    ]);

    SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'text' => 'How satisfied are you?',
        'type' => 'rating',
        'order' => 1,
    ]);

    SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'text' => 'Any comments?',
        'type' => 'text',
        'order' => 2,
    ]);

    return $survey->fresh(['questions']);
}

// ── Listing ───────────────────────────────────────────────────────────────────

it('lists active public surveys', function (): void {
    $visible = makePublicSurvey();
    $hidden = makePublicSurvey(active: false);

    $this->getJson('/api/v1/public/surveys')
        ->assertOk()
        ->assertJsonFragment(['title' => $visible->title])
        ->assertJsonMissing(['title' => $hidden->title]);
});

it('does not list private surveys', function (): void {
    $private = makePublicSurvey(public: false);

    $this->getJson('/api/v1/public/surveys')
        ->assertOk()
        ->assertJsonMissing(['title' => $private->title]);
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('shows an active public survey by slug', function (): void {
    $survey = makePublicSurvey();

    $this->getJson("/api/v1/public/surveys/{$survey->slug}")
        ->assertOk()
        ->assertJsonFragment(['title' => $survey->title]);
});

it('returns 404 for inactive survey', function (): void {
    $survey = makePublicSurvey(active: false);

    $this->getJson("/api/v1/public/surveys/{$survey->slug}")->assertNotFound();
});

it('returns 404 for private survey', function (): void {
    $survey = makePublicSurvey(public: false);

    $this->getJson("/api/v1/public/surveys/{$survey->slug}")->assertNotFound();
});

// ── Submit ────────────────────────────────────────────────────────────────────

it('stores anonymous responses successfully', function (): void {
    $survey = makePublicSurvey();
    $questions = $survey->questions;

    $payload = [
        'client_token' => (string) Str::uuid(),
        'answers' => [
            ['question_id' => $questions[0]->id, 'answer' => '4'],
            ['question_id' => $questions[1]->id, 'answer' => 'Great service!'],
        ],
    ];

    $this->postJson("/api/v1/public/surveys/{$survey->slug}/respond", $payload)
        ->assertCreated()
        ->assertJson(['submitted' => true]);

    expect(AnonymousSurveyResponse::where('survey_id', $survey->id)->count())->toBe(1);
    expect($survey->anonymousResponses->first()->answers->count())->toBe(2);
});

it('validates that question_id belongs to the survey', function (): void {
    $survey = makePublicSurvey();
    $otherSurvey = makePublicSurvey();
    $foreignQuestion = $otherSurvey->questions->first();

    $payload = [
        'client_token' => (string) Str::uuid(),
        'answers' => [
            ['question_id' => $foreignQuestion->id, 'answer' => '3'],
        ],
    ];

    $this->postJson("/api/v1/public/surveys/{$survey->slug}/respond", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('answers.0.question_id');
});

it('rejects submission to inactive survey', function (): void {
    $survey = makePublicSurvey(active: false);
    $question = $survey->questions->first();

    $this->postJson("/api/v1/public/surveys/{$survey->slug}/respond", [
        'client_token' => (string) Str::uuid(),
        'answers' => [
            ['question_id' => $question->id, 'answer' => 'test'],
        ],
    ])->assertNotFound();
});

it('requires at least one answer', function (): void {
    $survey = makePublicSurvey();

    $this->postJson("/api/v1/public/surveys/{$survey->slug}/respond", [
        'client_token' => (string) Str::uuid(),
        'answers' => [],
    ])->assertUnprocessable()->assertJsonValidationErrorFor('answers');
});

it('prevents duplicate submission from same session', function (): void {
    $survey = makePublicSurvey();
    $questions = $survey->questions;

    $token = (string) Str::uuid();
    $payload = [
        'client_token' => $token,
        'answers' => [
            ['question_id' => $questions[0]->id, 'answer' => '5'],
            ['question_id' => $questions[1]->id, 'answer' => 'Excellent!'],
        ],
    ];

    // First submission
    $this->postJson("/api/v1/public/surveys/{$survey->slug}/respond", $payload)
        ->assertCreated();

    // Second submission with same token returns already_submitted
    $this->postJson("/api/v1/public/surveys/{$survey->slug}/respond", $payload)
        ->assertOk()
        ->assertJson(['already_submitted' => true]);

    // Only one response in DB
    expect(AnonymousSurveyResponse::where('survey_id', $survey->id)->count())->toBe(1);
});

// ── Slug generation ───────────────────────────────────────────────────────────

it('generates a unique slug on survey creation', function (): void {
    $survey = Survey::factory()->create(['title' => 'My Test Survey']);

    expect($survey->slug)->toBe('my-test-survey');
});

it('appends a counter when slug already taken', function (): void {
    Survey::factory()->create(['title' => 'Duplicate Title']);
    $second = Survey::factory()->create(['title' => 'Duplicate Title']);

    expect($second->slug)->toBe('duplicate-title-1');
});

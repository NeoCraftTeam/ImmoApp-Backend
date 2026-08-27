<?php

declare(strict_types=1);

use App\Services\Ai\AiDescriptionEnhancer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Output-hygiene net for {@see AiDescriptionEnhancer}: the description paths
 * (enhance / generateFromAttributes / streamEnhance) must return a rich, COMPLETE
 * paragraph free of provider artifacts — no meta-preamble, enclosing quotes,
 * Markdown, or emojis — while the newsletter / lease-summary paths, which
 * legitimately carry HTML or emojis, must be left untouched.
 */
beforeEach(function (): void {
    config()->set('services.ai.provider', 'openai');
    config()->set('services.openai.api_key', 'sk-live-not-a-placeholder-1234567890');
    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.groq.api_key', '');
    config()->set('services.gemini.api_key', '');
});

/** Fake the OpenAI chat-completions endpoint with a single assistant message. */
function fakeOpenAiContent(string $content, ?string $finishReason = 'stop'): void
{
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => $content],
                'finish_reason' => $finishReason,
            ]],
        ], 200),
    ]);
}

it('strips a conversational meta-preamble from the enhanced description', function (): void {
    fakeOpenAiContent("Voici la description améliorée :\n\nBel appartement F3 à Douala, proche du marché.");

    $result = app(AiDescriptionEnhancer::class)->enhance('Appart 3 pièces Douala');

    expect($result)->not->toContain('Voici la description')
        ->and($result)->toStartWith('Bel appartement F3');
});

it('strips a labelled preamble like "Description enrichie :"', function (): void {
    fakeOpenAiContent('Description enrichie : Studio meublé lumineux au centre-ville.');

    $result = app(AiDescriptionEnhancer::class)->enhance('studio meuble');

    expect($result)->toBe('Studio meublé lumineux au centre-ville.');
});

it('does not over-strip a legitimate sentence that merely starts with "Voici"', function (): void {
    fakeOpenAiContent('Voici une maison spacieuse. Située à Bonapriso : proche des commerces.');

    $result = app(AiDescriptionEnhancer::class)->enhance('maison bonapriso');

    // The period before the colon guards the sentence — nothing should be removed.
    expect($result)->toBe('Voici une maison spacieuse. Située à Bonapriso : proche des commerces.');
});

it('strips enclosing quotes wrapping the whole description', function (): void {
    fakeOpenAiContent('« Bel appartement lumineux au calme. »');

    $result = app(AiDescriptionEnhancer::class)->enhance('appart');

    expect($result)->toBe('Bel appartement lumineux au calme.');
});

it('removes Markdown emphasis, headings and bullet markers', function (): void {
    fakeOpenAiContent("**Bel appartement F3**\n\n- Cuisine équipée\n- Balcon sud");

    $result = app(AiDescriptionEnhancer::class)->enhance('appart');

    expect($result)->not->toContain('**')
        ->and($result)->not->toContain('- ')
        ->and($result)->toContain('Bel appartement F3')
        ->and($result)->toContain('Cuisine équipée')
        ->and($result)->toContain('Balcon sud');
});

it('removes emojis from the enhanced description', function (): void {
    fakeOpenAiContent('Bel appartement 🏡 à Douala 🎉 très lumineux.');

    $result = app(AiDescriptionEnhancer::class)->enhance('appart');

    expect($result)->toBe('Bel appartement à Douala très lumineux.');
});

it('collapses runs of blank lines to a single paragraph break', function (): void {
    fakeOpenAiContent("Premier paragraphe.\n\n\n\n\nSecond paragraphe.");

    $result = app(AiDescriptionEnhancer::class)->enhance('appart');

    expect($result)->toBe("Premier paragraphe.\n\nSecond paragraphe.");
});

it('never mutates the original text when no provider is configured', function (): void {
    config()->set('services.openai.api_key', '');

    $original = 'Studio 🏡 meublé à louer — **dispo** maintenant.';
    $result = app(AiDescriptionEnhancer::class)->enhance($original);

    // Provider failure returns the input verbatim; cleanup must not touch it.
    expect($result)->toBe($original);
});

it('requests a token budget large enough to avoid truncation on the description path', function (): void {
    fakeOpenAiContent('Bel appartement.');

    app(AiDescriptionEnhancer::class)->enhance('appart');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'api.openai.com')
        && ($request->data()['max_tokens'] ?? 0) >= 1000);
});

it('cleans the generated-from-attributes description too', function (): void {
    fakeOpenAiContent('**Villa moderne** à Douala 🏡 avec jardin.');

    $result = app(AiDescriptionEnhancer::class)->generateFromAttributes([
        'type' => 'Villa',
        'city' => 'Douala',
    ]);

    expect($result)->toBe('Villa moderne à Douala avec jardin.');
});

it('does NOT strip emojis from the newsletter path (HTML/emoji intentional)', function (): void {
    fakeOpenAiContent('<p>Découvrez nos offres du mois 🎉</p>');

    $result = app(AiDescriptionEnhancer::class)->enhanceNewsletter('<p>offres</p>');

    expect($result)->toContain('🎉')
        ->and($result)->toContain('<p>');
});

it('does NOT strip emojis from the lease-summary path (emojis intentional)', function (): void {
    fakeOpenAiContent("💰 Loyer : 85 000 FCFA\n🔒 Caution : 170 000 FCFA");

    $result = app(AiDescriptionEnhancer::class)->summarizeLeaseContract([
        'monthly_rent' => 85000,
        'deposit_amount' => 170000,
    ]);

    expect($result)->toContain('💰')
        ->and($result)->toContain('🔒');
});

it('streams a cleaned, complete description reassembled from chunks', function (): void {
    $sse = implode("\n", [
        'data: {"choices":[{"delta":{"content":"Voici : "}}]}',
        '',
        'data: {"choices":[{"delta":{"content":"Bel appartement F3."}}]}',
        '',
        'data: {"choices":[{"delta":{"content":" Proche du marché."}}]}',
        '',
        'data: [DONE]',
        '',
    ]);

    Http::fake(['api.openai.com/*' => Http::response($sse, 200)]);

    $collected = '';
    app(AiDescriptionEnhancer::class)->streamEnhance('appart', function (string $chunk) use (&$collected): void {
        $collected .= $chunk;
    });

    expect($collected)->not->toContain('Voici :')
        ->and($collected)->toBe('Bel appartement F3. Proche du marché.');
});

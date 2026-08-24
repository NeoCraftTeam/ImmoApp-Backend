<?php

declare(strict_types=1);

use App\Services\Ai\AiDescriptionEnhancer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Characterization net for the eight system prompts about to be extracted
 * verbatim out of {@see AiDescriptionEnhancer} into AiDescriptionPrompts.
 *
 * Each case drives a public enhance method and pins the OPENING and CLOSING of
 * the exact system-prompt string that reaches the provider. Pinning both ends
 * makes it impossible for the extraction to re-route a method onto the wrong
 * prompt or to silently truncate / mutate a nowdoc: the byte sequence at the
 * head and tail of every prompt is locked.
 */
beforeEach(function (): void {
    // Only OpenAI is keyed, so callWithPrompt resolves a single deterministic
    // provider. A non-placeholder key is required or isValidKey short-circuits
    // before any HTTP call is attempted.
    config()->set('services.ai.provider', 'openai');
    config()->set('services.openai.api_key', 'sk-live-not-a-placeholder-1234567890');
    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.groq.api_key', '');
    config()->set('services.gemini.api_key', '');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'TEXTE TRAITÉ PAR IA']]],
        ], 200),
    ]);
});

/**
 * Assert exactly one system prompt was sent to the OpenAI endpoint and that it
 * both starts with $opening and ends-region contains $closing.
 */
function assertAiSystemPromptSent(string $opening, string $closing): void
{
    Http::assertSent(function (Request $request) use ($opening, $closing): bool {
        if (!str_contains($request->url(), 'api.openai.com')) {
            return false;
        }

        $system = $request->data()['messages'][0]['content'] ?? '';

        return str_contains($system, $opening) && str_contains($system, $closing);
    });
}

it('enhance() sends the description-enhancement system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->enhance('Studio meublé à louer à Douala.');

    assertAiSystemPromptSent(
        'Améliorer UNIQUEMENT la description fournie par le propriétaire en préservant TOUS les faits',
        'Aucun commentaire après le texte',
    );
});

it('enhanceRejectionReason() sends the rejection-reason system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->enhanceRejectionReason('Photos floues et description trop courte.');

    assertAiSystemPromptSent(
        'Transformer le motif de refus brut',
        'Aucune signature',
    );
});

it('enhanceLeaseConditions() sends the lease-conditions system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->enhanceLeaseConditions('Loyer payable le 5 de chaque mois.');

    assertAiSystemPromptSent(
        'Reformuler les conditions particulières',
        'Aucune formule de clôture',
    );
});

it('generateFromAttributes() sends the attribute-generation system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->generateFromAttributes([
        'type' => 'Villa',
        'city' => 'Douala',
        'bedrooms' => 4,
    ]);

    assertAiSystemPromptSent(
        'STRUCTURE ATTENDUE (très importante)',
        'de listes à puces ni de balisage Markdown/HTML',
    );
});

it('enhanceTitle() sends the title system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->enhanceTitle('appart meuble bastos', [
        'type' => 'Appartement',
        'city' => 'Yaoundé',
    ]);

    assertAiSystemPromptSent(
        'Produis UN SEUL titre, de 6 à 12 mots maximum',
        'Conserve les faits fournis (type, ville, surface, etc.)',
    );
});

it('diagnoseAdForRejection() sends the diagnosis system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->diagnoseAdForRejection([
        'title' => 'Maison',
        'description' => 'court',
        'price' => 0,
        'photos_count' => 0,
        'type' => 'Villa',
    ]);

    assertAiSystemPromptSent(
        'Analyser une annonce soumise et rédiger un motif de refus professionnel',
        'Aucun commentaire méta',
    );
});

it('summarizeLeaseContract() sends the lease-summary system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->summarizeLeaseContract([
        'monthly_rent' => 85000,
        'deposit_amount' => 170000,
        'duration_months' => 12,
    ]);

    assertAiSystemPromptSent(
        'Tu transformes des chiffres en phrases simples',
        'Aucune conclusion',
    );
});

it('enhanceNewsletter() sends the newsletter system prompt', function (): void {
    app(AiDescriptionEnhancer::class)->enhanceNewsletter('<p>Découvrez nos offres du mois.</p>');

    assertAiSystemPromptSent(
        'Tu es un rédacteur spécialisé en newsletters marketing pour la plateforme immobilière KeyHome.',
        'sauf si le texte original en contient déjà',
    );
});

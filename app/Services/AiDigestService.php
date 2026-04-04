<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\SearchAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates a concise, personalised French digest summary for a batch of
 * ads that matched a user's search alert.
 *
 * Uses the same multi-provider pattern as AiDescriptionEnhancer:
 *   - Primary provider: AI_PROVIDER env (default groq)
 *   - Automatic fallback to any other configured provider
 *   - Template-based fallback when no AI provider is available
 */
class AiDigestService
{
    /** @var array<string, array{api_key: string, model: string, base_url: string}> */
    private array $providers;

    private string $activeProvider;

    public function __construct()
    {
        $this->providers = [
            'openai' => [
                'api_key' => (string) config('services.openai.api_key', ''),
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'base_url' => 'https://api.openai.com/v1/chat/completions',
            ],
            'groq' => [
                'api_key' => (string) config('services.groq.api_key', ''),
                'model' => (string) config('services.groq.model', 'llama-3.3-70b-versatile'),
                'base_url' => 'https://api.groq.com/openai/v1/chat/completions',
            ],
            'gemini' => [
                'api_key' => (string) config('services.gemini.api_key', ''),
                'model' => (string) config('services.gemini.model', 'gemini-2.0-flash'),
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            ],
            'mistral' => [
                'api_key' => (string) config('services.mistral.api_key', ''),
                'model' => (string) config('services.mistral.model', 'mistral-small-latest'),
                'base_url' => 'https://api.mistral.ai/v1/chat/completions',
            ],
        ];

        $this->activeProvider = (string) config('services.ai.provider', 'groq');
    }

    /**
     * Generate a 1–2 sentence French digest summary for the given batch.
     *
     * @param  SearchAlert  $alert  The alert whose criteria produced the matches
     * @param  Ad[]  $ads  Ads that matched (already limited to a sane count)
     * @return string Human-readable summary in French
     */
    public function summarize(SearchAlert $alert, array $ads): string
    {
        $count = count($ads);

        if ($count === 0) {
            return '';
        }

        $fallback = $this->templateSummary($alert, $ads);

        if (!$this->hasProvider()) {
            return $fallback;
        }

        $payload = $this->buildPayload($alert, $ads);
        $result = $this->callWithFallback($payload);

        return $result !== '' ? $result : $fallback;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function buildPayload(SearchAlert $alert, array $ads): string
    {
        $alertDesc = $this->describeAlert($alert);

        $adLines = collect($ads)->take(5)->map(function (Ad $ad): string {
            $price = number_format((float) ($ad->price ?? 0), 0, ',', ' ').' FCFA';
            $location = $ad->quarter?->name ? "{$ad->quarter->name}" : '';
            $surface = $ad->surface_area ? "{$ad->surface_area} m²" : '';
            $rooms = $ad->bedrooms ? "{$ad->bedrooms} ch." : '';
            $details = implode(', ', array_filter([$surface, $rooms, $location]));

            return "- {$ad->title} | {$price}".($details !== '' ? " | {$details}" : '');
        })->implode("\n");

        $remaining = count($ads) > 5 ? ' (et '.(count($ads) - 5).' autre(s))' : '';

        return "Alerte : {$alertDesc}\nNombre de nouvelles annonces : ".count($ads)."\n\nAnnonces{$remaining} :\n{$adLines}";
    }

    private function describeAlert(SearchAlert $alert): string
    {
        if ($alert->label) {
            return $alert->label;
        }

        $parts = array_filter([
            $alert->type_name,
            $alert->city_name,
            $alert->price_max ? 'max '.number_format($alert->price_max, 0, ',', ' ').' FCFA' : null,
        ]);

        return implode(' à ', $parts) ?: 'Alerte immobilière';
    }

    /**
     * Template-based fallback — always works, even without an AI key.
     *
     * @param  Ad[]  $ads
     */
    private function templateSummary(SearchAlert $alert, array $ads): string
    {
        $count = count($ads);
        $label = $alert->label ?? $this->describeAlert($alert);
        $noun = $count === 1 ? 'nouvelle annonce correspond' : 'nouvelles annonces correspondent';

        $minPrice = collect($ads)->min('price');
        $maxPrice = collect($ads)->max('price');

        $priceHint = '';
        if ($minPrice !== null) {
            if ($minPrice === $maxPrice) {
                $priceHint = ' à partir de '.number_format((float) $minPrice, 0, ',', ' ').' FCFA';
            } else {
                $priceHint = ' entre '.number_format((float) $minPrice, 0, ',', ' ').' et '.number_format((float) $maxPrice, 0, ',', ' ').' FCFA';
            }
        }

        return "{$count} {$noun} à votre alerte « {$label} »{$priceHint}.";
    }

    private function hasProvider(): bool
    {
        return array_any($this->providers, fn ($cfg) => $this->isValidKey($cfg['api_key']));
    }

    private function callWithFallback(string $payload): string
    {
        $config = $this->providers[$this->activeProvider] ?? null;

        if ($config === null || !$this->isValidKey($config['api_key'])) {
            foreach ($this->providers as $name => $cfg) {
                if ($this->isValidKey($cfg['api_key'])) {
                    $this->activeProvider = $name;
                    $config = $cfg;
                    break;
                }
            }
        }

        if ($config === null || !$this->isValidKey($config['api_key'])) {
            return '';
        }

        return $this->activeProvider === 'gemini'
            ? $this->callGemini($payload, $config)
            : $this->callOpenAiCompatible($payload, $config);
    }

    /**
     * @param  array{api_key: string, model: string, base_url: string}  $config
     */
    private function callOpenAiCompatible(string $payload, array $config): string
    {
        try {
            $response = Http::withToken($config['api_key'])
                ->timeout(20)
                ->post($config['base_url'], [
                    'model' => $config['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user',   'content' => $payload],
                    ],
                    'max_tokens' => 120,
                    'temperature' => 0.5,
                ]);

            if ($response->failed()) {
                Log::warning('AiDigestService ('.$this->activeProvider.'): API error', [
                    'status' => $response->status(),
                ]);

                return '';
            }

            return trim((string) ($response->json('choices.0.message.content') ?? ''));
        } catch (\Throwable $e) {
            Log::error('AiDigestService ('.$this->activeProvider.'): '.$e->getMessage());

            return '';
        }
    }

    /**
     * @param  array{api_key: string, model: string, base_url: string}  $config
     */
    private function callGemini(string $payload, array $config): string
    {
        $url = $config['base_url'].'/'.$config['model'].':generateContent?key='.$config['api_key'];

        try {
            $response = Http::timeout(20)->post($url, [
                'system_instruction' => ['parts' => [['text' => $this->systemPrompt()]]],
                'contents' => [['parts' => [['text' => $payload]]]],
                'generationConfig' => ['maxOutputTokens' => 120, 'temperature' => 0.5],
            ]);

            if ($response->failed()) {
                Log::warning('AiDigestService (gemini): API error', ['status' => $response->status()]);

                return '';
            }

            return trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
        } catch (\Throwable $e) {
            Log::error('AiDigestService (gemini): '.$e->getMessage());

            return '';
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant pour la plateforme immobilière KeyHome (Afrique centrale, Cameroun/UEMOA).

TON UNIQUE RÔLE : rédiger un résumé court (1 à 2 phrases maximum) en français d'un lot d'annonces immobilières qui correspondent à l'alerte de recherche d'un utilisateur.

RÈGLES STRICTES :
- Mentionne le nombre exact d'annonces.
- Indique le nom de l'alerte ou les critères principaux (ville, type, fourchette de prix).
- Si pertinent, précise la fourchette de prix observée dans les annonces.
- Ne liste PAS les annonces une par une — c'est le rôle de l'email, pas du résumé.
- Ton neutre, informatif, chaleureux. Maximum 2 phrases. Pas d'emojis. Pas de titre.
- Renvoie UNIQUEMENT le résumé, sans explication ni commentaire.
PROMPT;
    }

    private function isValidKey(string $key): bool
    {
        $key = trim($key);
        $stripped = preg_replace('/^(sk-|gsk_|AIza)/i', '', $key);

        return $key !== '' && $stripped !== '' && !preg_match('/^x+$/i', (string) $stripped);
    }
}

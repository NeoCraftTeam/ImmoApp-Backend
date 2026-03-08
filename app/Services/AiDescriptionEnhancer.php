<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-provider AI description enhancer.
 *
 * Supported providers (set via AI_PROVIDER env var):
 *   - openai : OpenAI GPT models      (https://api.openai.com)
 *   - groq   : Groq LLMs              (https://api.groq.com/openai) — OpenAI-compatible
 *   - gemini : Google Gemini          (https://generativelanguage.googleapis.com)
 */
class AiDescriptionEnhancer
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
        ];

        $this->activeProvider = (string) config('services.ai.provider', 'openai');
    }

    /**
     * Enhance a real-estate ad description using the configured AI provider.
     * Falls back to any other provider that has a key if the primary is not configured.
     * Returns the enhanced text, or the original if the call fails.
     */
    public function enhance(string $rawDescription): string
    {
        return $this->callWithPrompt($rawDescription, $this->systemPrompt());
    }

    /**
     * Enhance an ad rejection reason to be professional, clear, and courteous.
     * Returns the enhanced text, or the original if the call fails.
     */
    public function enhanceRejectionReason(string $rawReason): string
    {
        return $this->callWithPrompt($rawReason, $this->rejectionReasonPrompt());
    }

    /**
     * Resolve the active provider config with fallback, then call the appropriate API.
     */
    private function callWithPrompt(string $text, string $systemPrompt): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $config = $this->providers[$this->activeProvider] ?? null;

        if ($config === null || empty($config['api_key'])) {
            foreach ($this->providers as $name => $cfg) {
                if (!empty($cfg['api_key'])) {
                    $this->activeProvider = $name;
                    $config = $cfg;
                    break;
                }
            }
        }

        if ($config === null || empty($config['api_key'])) {
            Log::warning('AiDescriptionEnhancer: no AI provider is configured.');

            return $text;
        }

        return $this->activeProvider === 'gemini'
            ? $this->callGemini($text, $config, $systemPrompt)
            : $this->callOpenAiCompatible($text, $config, $systemPrompt);
    }

    /**
     * Call an OpenAI-compatible endpoint (OpenAI & Groq share the same payload format).
     *
     * @param  array{api_key: string, model: string, base_url: string}  $config
     */
    private function callOpenAiCompatible(string $text, array $config, string $systemPrompt): string
    {
        try {
            $response = Http::withToken($config['api_key'])
                ->timeout(25)
                ->post($config['base_url'], [
                    'model' => $config['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $text],
                    ],
                    'max_tokens' => 400,
                    'temperature' => 0.7,
                ]);

            if ($response->failed()) {
                Log::warning('AI ('.$this->activeProvider.') enhancement failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $text;
            }

            return trim((string) ($response->json('choices.0.message.content') ?? $text));
        } catch (\Throwable $e) {
            Log::error('AI ('.$this->activeProvider.') enhancement exception: '.$e->getMessage());

            return $text;
        }
    }

    /**
     * Call the Google Gemini API (different endpoint & payload structure).
     *
     * @param  array{api_key: string, model: string, base_url: string}  $config
     */
    private function callGemini(string $text, array $config, string $systemPrompt): string
    {
        $url = $config['base_url'].'/'.$config['model'].':generateContent?key='.$config['api_key'];

        try {
            $response = Http::timeout(25)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        ['parts' => [['text' => $text]]],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 400,
                        'temperature' => 0.7,
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('AI (gemini) enhancement failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $text;
            }

            return trim((string) ($response->json('candidates.0.content.parts.0.text') ?? $text));
        } catch (\Throwable $e) {
            Log::error('AI (gemini) enhancement exception: '.$e->getMessage());

            return $text;
        }
    }

    private function systemPrompt(): string
    {
        return "Tu es un expert en rédaction d'annonces immobilières pour la plateforme KeyHome, spécialisée dans l'immobilier en Afrique centrale (principalement au Cameroun).\n"
            ."Ton rôle est d'améliorer la description d'une annonce fournie par un propriétaire.\n"
            ."Règles :\n"
            ."- Rédige en français, de façon professionnelle, claire et attrayante.\n"
            ."- Mets en valeur les atouts du bien (espace, luminosité, accès, sécurité, commodités).\n"
            ."- Conserve toutes les informations factuelles mentionnées par le propriétaire.\n"
            ."- Ajoute du contexte local si approprié (ex : quartier calme, proche du centre-ville).\n"
            ."- Longueur optimale : 80 à 200 mots.\n"
            ."- N'invente aucune information absente du texte original.\n"
            .'- Renvoie uniquement la description améliorée, sans introduction ni explication.';
    }

    private function rejectionReasonPrompt(): string
    {
        return "Tu es un modérateur professionnel pour la plateforme immobilière KeyHome (Afrique centrale).\n"
            ."Ton rôle est de reformuler un motif de refus d'annonce pour qu'il soit clair, professionnel et respectueux envers le propriétaire.\n"
            ."Règles :\n"
            ."- Rédige en français, de façon polie et constructive.\n"
            ."- Explique clairement pourquoi l'annonce est refusée.\n"
            ."- Indique quelles corrections le propriétaire doit apporter pour soumettre à nouveau.\n"
            ."- Conserve toutes les raisons mentionnées, sans en inventer.\n"
            ."- Longueur optimale : 30 à 100 mots.\n"
            .'- Renvoie uniquement le motif reformulé, sans introduction ni explication.';
    }
}

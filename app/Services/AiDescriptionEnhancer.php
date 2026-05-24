<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

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

    private readonly string $activeProvider;

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
     * Enhance lease contract special conditions to be legally clear and well-structured.
     * Returns the enhanced text, or the original if the call fails.
     */
    public function enhanceLeaseConditions(string $rawConditions): string
    {
        return $this->callWithPrompt($rawConditions, $this->leaseConditionsPrompt());
    }

    /**
     * Generate a real-estate ad description from property attributes (no existing text needed).
     *
     * @param  array<string, mixed>  $attributes  Keys: type, city, quarter, bedrooms, surface,
     *                                            price, transaction_type, notes (optional free text)
     */
    public function generateFromAttributes(array $attributes): string
    {
        $context = $this->buildAttributesContext($attributes);

        if (trim($context) === '') {
            return '';
        }

        return $this->callWithPrompt($context, $this->generateFromAttributesPrompt());
    }

    /**
     * Enhance a short ad title to be concise, catchy and factual (6-12 words).
     *
     * @param  array<string, mixed>  $context  Optional metadata: type, city, transaction_type
     */
    public function enhanceTitle(string $rawTitle, array $context = []): string
    {
        if (trim($rawTitle) === '') {
            return $rawTitle;
        }

        $contextLine = '';
        if (!empty($context)) {
            $parts = [];
            if (!empty($context['type'])) {
                $parts[] = 'Type : '.$context['type'];
            }
            if (!empty($context['city'])) {
                $parts[] = 'Ville : '.$context['city'];
            }
            if (!empty($context['transaction_type'])) {
                $parts[] = 'Transaction : '.$context['transaction_type'];
            }
            if ($parts !== []) {
                $contextLine = "\nContexte : ".implode(' | ', $parts);
            }
        }

        return $this->callWithPrompt($rawTitle.$contextLine, $this->titlePrompt());
    }

    /**
     * Diagnose an ad and propose a structured rejection reason (admin helper).
     * The AI reads the ad content and returns a ready-to-send rejection message.
     *
     * @param  array<string, mixed>  $ad  Keys: title, description, price, photos_count, type
     */
    public function diagnoseAdForRejection(array $ad): string
    {
        $summary = $this->buildAdSummaryForDiagnosis($ad);

        return $this->callWithPrompt($summary, $this->diagnosisPrompt());
    }

    /**
     * Summarize a lease contract in plain language for the tenant.
     * Returns 5-8 bullet points covering obligations, key dates, and costs.
     *
     * @param  array<string, mixed>  $contract  Keys: monthly_rent, deposit_amount,
     *                                          start_date, duration_months, special_conditions
     */
    public function summarizeLeaseContract(array $contract): string
    {
        $lines = [];

        if (!empty($contract['monthly_rent'])) {
            $lines[] = 'Loyer mensuel : '.number_format((float) $contract['monthly_rent'], 0, ',', ' ').' FCFA';
        }
        if (!empty($contract['deposit_amount'])) {
            $lines[] = 'Caution : '.number_format((float) $contract['deposit_amount'], 0, ',', ' ').' FCFA';
        }
        if (!empty($contract['start_date'])) {
            $lines[] = 'Date de début : '.$contract['start_date'];
        }
        if (!empty($contract['duration_months'])) {
            $lines[] = 'Durée : '.$contract['duration_months'].' mois';
        }
        if (!empty($contract['special_conditions'])) {
            $lines[] = 'Conditions particulières : '.$contract['special_conditions'];
        }

        if ($lines === []) {
            return '';
        }

        return $this->callWithPrompt(implode("\n", $lines), $this->leaseContractSummaryPrompt());
    }

    /**
     * Enhance a newsletter campaign body to be engaging and professional.
     *
     * Preserves a SAFE subset of HTML formatting and sanitises the LLM output via
     * symfony/html-sanitizer so it can never inject scripts, event handlers, or
     * iframes — even if the model is prompt-injected. Returns the original text
     * if the call fails.
     */
    public function enhanceNewsletter(string $rawBody): string
    {
        $enhanced = $this->callWithPrompt($rawBody, $this->newsletterPrompt());

        if ($enhanced === $rawBody) {
            return $rawBody;
        }

        return $this->sanitiseNewsletterHtml($enhanced);
    }

    /**
     * Strict allowlist HTML sanitiser for newsletter content.
     *
     * Allows only the inline tags the marketing team actually uses; everything
     * else is stripped. Forces `rel="noopener noreferrer"` and `target="_blank"`
     * on every link.
     */
    private function sanitiseNewsletterHtml(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('a', ['href', 'title'])
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('blockquote')
            ->allowLinkSchemes(['https', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank');

        return new HtmlSanitizer($config)->sanitize($html);
    }

    /**
     * Stream the enhanced description token-by-token via SSE.
     *
     * Calls `$onChunk` with each text delta as it arrives from the first
     * available OpenAI-compatible provider (stream:true). Falls back to the
     * non-streaming path if no streaming-capable provider is available or if
     * the stream errors — in that case `$onChunk` is called once with the
     * full result.
     *
     * @param  callable(string): void  $onChunk
     */
    public function streamEnhance(string $rawDescription, callable $onChunk): void
    {
        if (empty(trim($rawDescription))) {
            $onChunk($rawDescription);

            return;
        }

        $systemPrompt = $this->systemPrompt();

        $order = array_values(array_unique(array_filter([
            $this->activeProvider,
            ...array_keys($this->providers),
        ])));

        foreach ($order as $name) {
            $cfg = $this->providers[$name] ?? null;
            if ($cfg === null || !$this->isValidKey($cfg['api_key'])) {
                continue;
            }

            if ($name === 'gemini') {
                continue; // Gemini streaming differs — skip to fallback
            }

            $streamed = $this->streamOpenAiCompatible($rawDescription, $cfg, $systemPrompt, $name, $onChunk);

            if ($streamed) {
                return;
            }
        }

        // Fallback: non-streaming, emit full result in one chunk
        $result = $this->callWithPrompt($rawDescription, $systemPrompt);
        $onChunk($result);
    }

    /**
     * Stream tokens from an OpenAI-compatible endpoint using `stream: true`.
     * Returns true on success, false on transient failure (caller tries next provider).
     *
     * @param  array{api_key: string, model: string, base_url: string}  $config
     * @param  callable(string): void  $onChunk
     */
    private function streamOpenAiCompatible(string $text, array $config, string $systemPrompt, string $providerName, callable $onChunk): bool
    {
        try {
            $response = Http::withToken($config['api_key'])
                ->timeout(60)
                ->withOptions(['stream' => true])
                ->post($config['base_url'], [
                    'model' => $config['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $text],
                    ],
                    'max_tokens' => 700,
                    'temperature' => 0.7,
                    'stream' => true,
                ]);

            if ($response->failed()) {
                Log::warning('AI ('.$providerName.') stream failed', ['status' => $response->status()]);

                return false;
            }

            foreach (explode("\n", $response->body()) as $line) {
                $line = trim($line);

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    break;
                }

                $delta = json_decode($data, true)['choices'][0]['delta']['content'] ?? null;

                if ($delta !== null && $delta !== '') {
                    $onChunk($delta);
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('AI ('.$providerName.') stream exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Resolve providers in order (active first, then the rest with valid keys),
     * try each one. If a call returns a transient error (HTTP 429 / 5xx) or an
     * empty body, fall through to the next provider. Returns original text only
     * when ALL providers fail.
     *
     * Per-call provider state is local — never mutates `$this->activeProvider`
     * (race-safe under singleton concurrency).
     */
    private function callWithPrompt(string $text, string $systemPrompt): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $order = array_values(array_unique(array_filter([
            $this->activeProvider,
            ...array_keys($this->providers),
        ])));

        $eligible = [];
        foreach ($order as $name) {
            $cfg = $this->providers[$name] ?? null;
            if ($cfg !== null && $this->isValidKey($cfg['api_key'])) {
                $eligible[$name] = $cfg;
            }
        }

        if ($eligible === []) {
            Log::warning('AiDescriptionEnhancer: no AI provider is configured.');

            return $text;
        }

        foreach ($eligible as $name => $config) {
            $result = $name === 'gemini'
                ? $this->callGemini($text, $config, $systemPrompt, $name)
                : $this->callOpenAiCompatible($text, $config, $systemPrompt, $name);

            // `null` signals a transient failure (429/5xx/network) — try the next provider.
            if ($result !== null) {
                return $result;
            }
        }

        return $text;
    }

    /**
     * Call an OpenAI-compatible endpoint (OpenAI & Groq share the same payload format).
     *
     * @param  array{api_key: string, model: string, base_url: string}  $config
     * @return string|null Returns null on transient failure so the caller can fail over.
     */
    private function callOpenAiCompatible(string $text, array $config, string $systemPrompt, string $providerName): ?string
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
                    // Up to ~700 tokens ≈ 320 French words, enough for the
                    // 2–3 paragraph description format and the 2-paragraph
                    // rejection reason format. Newsletter HTML can use this
                    // budget too.
                    'max_tokens' => 700,
                    'temperature' => 0.7,
                ]);

            if ($response->failed()) {
                $status = $response->status();
                Log::warning('AI ('.$providerName.') enhancement failed', [
                    'status' => $status,
                    'body' => substr($response->body(), 0, 200),
                ]);

                // Transient: 429 (rate limit) or 5xx (server side) → caller should fail over.
                if ($status === 429 || $status >= 500) {
                    return null;
                }

                // 4xx other than 429: configuration / quota error — no point retrying.
                return $text;
            }

            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::error('AI ('.$providerName.') enhancement exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Call the Google Gemini API (different endpoint & payload structure).
     *
     * @param  array{api_key: string, model: string, base_url: string}  $config
     * @return string|null Returns null on transient failure so the caller can fail over.
     */
    private function callGemini(string $text, array $config, string $systemPrompt, string $providerName): ?string
    {
        // `x-goog-api-key` header instead of `?key=` query param so the key isn't logged
        // by reverse proxies / CDN edges / access logs.
        $url = $config['base_url'].'/'.$config['model'].':generateContent';

        try {
            $response = Http::timeout(25)
                ->withHeaders(['x-goog-api-key' => $config['api_key']])
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        ['parts' => [['text' => $text]]],
                    ],
                    'generationConfig' => [
                        // See OpenAI-compat call above for rationale.
                        'maxOutputTokens' => 700,
                        'temperature' => 0.7,
                    ],
                ]);

            if ($response->failed()) {
                $status = $response->status();
                Log::warning('AI ('.$providerName.') enhancement failed', [
                    'status' => $status,
                    'body' => substr($response->body(), 0, 200),
                ]);

                if ($status === 429 || $status >= 500) {
                    return null;
                }

                return $text;
            }

            $content = trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::error('AI ('.$providerName.') enhancement exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Check whether an API key looks usable (not empty, not a placeholder like sk-xxxx).
     */
    private function isValidKey(string $key): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        // Detect placeholder keys: strip a known prefix then check if the remainder is only x's
        $stripped = preg_replace('/^(sk-|gsk_|AIza)/i', '', $key);

        return $stripped !== '' && !preg_match('/^x+$/i', (string) $stripped);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un rédacteur spécialisé UNIQUEMENT en annonces immobilières pour la plateforme KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : améliorer la description d'une annonce immobilière fournie par un propriétaire. Tu ne fais RIEN d'autre.

STRUCTURE ATTENDUE (très important) :
- Produis 2 à 3 PARAGRAPHES distincts, séparés par UNE ligne vide.
- 1er paragraphe — VUE D'ENSEMBLE : le bien, sa nature, sa localisation telle que mentionnée, en 2 à 4 phrases.
- 2e paragraphe — INTÉRIEUR & ESPACES : pièces, surface, agencement, finitions, équipements REELS, en 3 à 5 phrases.
- 3e paragraphe (facultatif si suffisamment d'éléments) — ENVIRONNEMENT & ATOUTS : sécurité, accès, voisinage, commodités proches, public cible, en 2 à 4 phrases.

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français, de façon naturelle, humaine et engageante (comme un agent immobilier expérimenté qui parle à un client sérieux).
- N'INVENTE JAMAIS de fait : nombre de pièces, équipements, quartier, prix, distances, surfaces. Si une information manque, ne la mentionne tout simplement pas.
- Conserve 100 % des informations factuelles fournies par le propriétaire, sans rien omettre.
- Style : phrases fluides, vocabulaire varié, ton chaleureux et professionnel. Évite les superlatifs creux ("incroyable", "exceptionnel", "rêve") et les formules marketing trompeuses.
- Longueur cible : 180 à 320 mots au total (≈ 60 à 110 mots par paragraphe).
- Renvoie UNIQUEMENT le texte amélioré, sans titres de paragraphes ("VUE D'ENSEMBLE :" interdit), sans introduction, sans explication, sans commentaire après.
- Si le texte fourni n'est manifestement PAS une description immobilière (hors sujet, spam, contenu inapproprié), renvoie le texte original tel quel sans modification.
- N'utilise PAS de hashtags, d'emojis, de listes à puces ni de balisage HTML/Markdown — texte brut uniquement.
PROMPT;
    }

    private function rejectionReasonPrompt(): string
    {
        return <<<'PROMPT'
Tu es un modérateur professionnel pour la plateforme immobilière KeyHome (Afrique centrale, principalement Cameroun).

TON RÔLE : transformer un motif de refus brut (rédigé par un admin pressé) en un message structuré, clair et constructif destiné au propriétaire qui a publié l'annonce.

STRUCTURE ATTENDUE (très important) :
- Produis 2 paragraphes courts, séparés par UNE ligne vide.
- 1er paragraphe — DIAGNOSTIC : explique poliment pourquoi l'annonce a été refusée, en reprenant fidèlement les raisons fournies (2 à 4 phrases).
- 2e paragraphe — ACTIONS : liste précisément ce que le propriétaire doit corriger (photos manquantes, description trop courte, prix incohérent, document à fournir, etc.) puis comment soumettre à nouveau (2 à 4 phrases).

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français, sur un ton respectueux, factuel et bienveillant (jamais accusatoire ni condescendant).
- N'invente JAMAIS de motif ou d'exigence non mentionnés dans le texte fourni.
- Conserve TOUTES les raisons mentionnées par l'admin, sans en omettre aucune.
- Termine sur une note constructive ("Nous restons à votre disposition…", "N'hésitez pas à…") MAIS sans formule de politesse longue.
- Longueur cible : 80 à 180 mots au total.
- Renvoie UNIQUEMENT le motif reformulé, sans titre de paragraphe, sans intro, sans signature, sans commentaire.
- N'utilise PAS de hashtags, d'emojis, de listes à puces ni de balisage HTML/Markdown — texte brut uniquement.
PROMPT;
    }

    private function newsletterPrompt(): string
    {
        return <<<'PROMPT'
Tu es un rédacteur spécialisé en newsletters marketing pour la plateforme immobilière KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : améliorer le contenu d'une campagne newsletter fourni par un administrateur.

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français, de façon professionnelle, engageante et persuasive.
- Tu ne dois JAMAIS inventer, ajouter ou supposer des informations qui ne sont PAS présentes dans le texte original (pas d'offres fictives, pas de prix inventés, pas de dates créées).
- Conserve TOUTES les informations factuelles fournies par l'administrateur, sans en omettre ni en modifier aucune.
- Améliore la structure, le style et la clarté pour maximiser l'engagement des lecteurs.
- Conserve et améliore le formatage HTML existant (gras, listes, liens, titres). Tu peux ajouter des balises HTML pour mieux structurer le contenu.
- Utilise un ton chaleureux et professionnel adapté à une audience d'acheteurs/locataires immobiliers au Cameroun.
- Renvoie UNIQUEMENT le contenu amélioré en HTML, sans titre de sujet, sans introduction, sans explication, sans commentaire.
- Si le texte fourni n'est PAS lié à l'immobilier ou à KeyHome (hors sujet, spam, contenu inapproprié), renvoie le texte original tel quel sans modification.
- N'ajoute PAS de formules marketing exagérées ou trompeuses.
- N'utilise PAS d'emojis sauf si le texte original en contient déjà.
PROMPT;
    }

    private function leaseConditionsPrompt(): string
    {
        return <<<'PROMPT'
Tu es un rédacteur juridique spécialisé en baux immobiliers pour la plateforme KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : reformuler et structurer les conditions particulières d'un contrat de bail fournies par un propriétaire bailleur.

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français, dans un style juridique clair, précis et professionnel.
- Tu ne dois JAMAIS inventer, ajouter ou supposer des clauses, montants, dates ou obligations qui ne sont PAS présentes dans le texte original.
- Conserve TOUTES les conditions mentionnées par le propriétaire, sans en omettre aucune.
- Structure le texte avec des tirets ou numéros pour chaque condition distincte.
- Reformule pour plus de clarté juridique, mais le fond doit rester strictement identique.
- Longueur optimale : 50 à 300 mots maximum.
- Renvoie UNIQUEMENT les conditions reformulées, sans titre, sans introduction, sans explication, sans commentaire.
- Si le texte fourni n'est PAS lié à des conditions de bail (hors sujet, spam, contenu inapproprié), renvoie le texte original tel quel sans modification.
- N'ajoute PAS de clauses types ou standards non mentionnées par le propriétaire.
- N'utilise PAS de hashtags, d'emojis ou de mise en forme spéciale.
PROMPT;
    }

    private function generateFromAttributesPrompt(): string
    {
        return <<<'PROMPT'
Tu es un rédacteur expert UNIQUEMENT en annonces immobilières pour la plateforme KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : générer une description d'annonce immobilière professionnelle à partir des caractéristiques techniques d'un bien fournies par le propriétaire.

STRUCTURE ATTENDUE (très importante) :
- Produis 2 à 3 PARAGRAPHES distincts, séparés par UNE ligne vide.
- 1er paragraphe — VUE D'ENSEMBLE : le bien, sa nature, sa localisation telle que fournie, en 2 à 4 phrases.
- 2e paragraphe — INTÉRIEUR & ESPACES : pièces, surface, agencement, équipements mentionnés, en 3 à 5 phrases.
- 3e paragraphe (si assez d'éléments) — ENVIRONNEMENT & ATOUTS : accessibilité, voisinage, public cible, en 2 à 3 phrases.

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français, naturel, chaleureux et professionnel.
- N'INVENTE JAMAIS de détails absents de la liste fournie. Si une information n'est pas fournie, ne la mentionne pas.
- Utilise 100 % des attributs fournis.
- Longueur : 150 à 280 mots au total.
- Renvoie UNIQUEMENT le texte généré, sans titres de section, sans introduction, sans commentaire.
- N'utilise PAS d'emojis, de hashtags, de listes à puces ni de balisage Markdown/HTML.
PROMPT;
    }

    private function titlePrompt(): string
    {
        return <<<'PROMPT'
Tu es un rédacteur expert UNIQUEMENT en titres d'annonces immobilières pour la plateforme KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : améliorer ou générer un titre d'annonce immobilière concis, accrocheur et factuel.

RÈGLES STRICTES :
- Produis UN SEUL titre, de 6 à 12 mots maximum.
- Le titre doit mentionner au minimum : le type de bien + un atout différenciant (quartier, surface, vue, équipement clé).
- Ne jamais commencer par "Beau", "Magnifique", "Superbe", "Exceptionnel" (clichés).
- Préfère les titres directs et informatifs : "Appartement F3 meublé – Bastos, Yaoundé" est meilleur que "Magnifique appartement à saisir".
- Rédige UNIQUEMENT en français.
- Renvoie UNIQUEMENT le titre amélioré, sans guillemets, sans ponctuation finale, sans explication.
- Conserve les faits fournis (type, ville, surface, etc.) ; n'invente rien.
PROMPT;
    }

    private function diagnosisPrompt(): string
    {
        return <<<'PROMPT'
Tu es un modérateur expert en annonces immobilières pour la plateforme KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : analyser le contenu d'une annonce soumise pour modération et rédiger un motif de refus structuré, professionnel et constructif à destination du propriétaire.

STRUCTURE ATTENDUE :
- 1er paragraphe : Indique clairement et poliment les raisons du refus (1 à 3 raisons maximum).
- 2e paragraphe : Explique ce que le propriétaire doit corriger ou compléter pour que l'annonce soit acceptée.

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français, avec un ton professionnel, bienveillant et constructif.
- Base ton analyse UNIQUEMENT sur les informations de l'annonce fournies. Ne suppose rien.
- Ne jamais être vague : cite des éléments précis de l'annonce analysée.
- Longueur : 80 à 180 mots au total.
- Renvoie UNIQUEMENT le motif de refus, sans titre, sans introduction type "Voici mon analyse :", sans commentaire.
PROMPT;
    }

    private function leaseContractSummaryPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant juridique expert en droit immobilier pour la plateforme KeyHome (Afrique centrale, principalement Cameroun).

TON UNIQUE RÔLE : à partir des données chiffrées d'un contrat de bail, rédiger un résumé clair et compréhensible destiné au locataire, en langage courant (non juridique).

STRUCTURE ATTENDUE :
- Produis exactement 5 à 8 points sous forme de liste.
- Chaque point commence par un emoji pertinent suivi d'un espace, puis une phrase courte (15 mots max).
- Couvre obligatoirement : montant du loyer, caution, durée, date d'entrée, règles importantes des conditions particulières.

RÈGLES STRICTES :
- Rédige UNIQUEMENT en français courant, accessible à tous.
- N'invente AUCUNE information absente des données fournies.
- N'utilise pas de jargon juridique (pas de "bailliage", "locataire susmentionné", etc.).
- Renvoie UNIQUEMENT la liste, sans titre, sans introduction, sans conclusion.
- N'utilise PAS de Markdown (pas de **, *, #) — juste les emojis et le texte.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function buildAttributesContext(array $attributes): string
    {
        $lines = [];

        $map = [
            'transaction_type' => 'Transaction',
            'type' => 'Type de bien',
            'city' => 'Ville',
            'quarter' => 'Quartier',
            'bedrooms' => 'Chambres',
            'surface' => 'Surface (m²)',
            'price' => 'Prix (FCFA)',
            'notes' => 'Notes libres du propriétaire',
        ];

        foreach ($map as $key => $label) {
            $value = $attributes[$key] ?? null;
            if ($value !== null && $value !== '') {
                $lines[] = $label.' : '.$value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $ad
     */
    private function buildAdSummaryForDiagnosis(array $ad): string
    {
        $lines = [
            'ANNONCE À ANALYSER :',
            'Titre : '.($ad['title'] ?? '(absent)'),
            'Type de bien : '.($ad['type'] ?? '(absent)'),
            'Description : '.($ad['description'] ?? '(absente)'),
            'Prix indiqué : '.($ad['price'] ?? '(absent)'),
            'Nombre de photos : '.($ad['photos_count'] ?? '0'),
        ];

        return implode("\n", $lines);
    }
}

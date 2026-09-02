<?php

declare(strict_types=1);

namespace App\Support;

final class SafeApiMessage
{
    /**
     * Patterns that indicate a backend-internal message that must never reach the UI.
     *
     * Mirrors keyhome-frontend-next/src/lib/error-messages.ts UNSAFE_PATTERNS.
     *
     * @var string[]
     */
    private const array UNSAFE_PATTERNS = [
        '/\bapi\/v\d/i',
        '/\broute\b/i',
        '/\bcontroller\b/i',
        '/\bnamespace\b/i',
        '/\bSQLSTATE\b/i',
        '/\bclass\s+[A-Z][\w\\\\]+/',
        '/\bcould not be found\b/i',
        '/\bnot found\b/i',
        '/\bundefined\s+(method|index|variable|property|offset)/i',
        '/\bcall to (a member|undefined)/i',
        '/\bnull on type\b/i',
        '/\bTrace:/i',
        '/\b(Exception|Error)\b.{0,60}\bin\s+\/?\w+/i',
        '/https?:\/\/\S+/i',
        '/\b(127\.0\.0\.1|localhost|0\.0\.0\.0)\b/i',
        '/\.env\b/i',
        '/\bphp\b/i',
        '/\bLaravel\b/i',
        '/\bMeilisearch\b/i',
        '/\bPostgres(?:ql)?\b/i',
        // Env var / config key names (ORS_API_KEY, STRIPE_SECRET…). Telling the
        // caller which credential is missing maps out the deployment; the case
        // is deliberately sensitive so French copy in caps stays safe.
        '/\b[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+\b/',
    ];

    /**
     * Leaked authorization hints (role/panel enumeration). Accented — the `u`
     * modifier is mandatory: without it preg_match walks the multibyte «é/è»
     * byte-by-byte and these patterns never match, silently leaking the hint.
     *
     * @var string[]
     */
    private const array LEAKY_AUTH_PATTERNS = [
        '/panneau administrateur/iu',
        '/panneau propri[ée]taire/iu',
        '/panneau bailleur/iu',
        '/acc[èe]s r[ée]serv[ée] aux clients/iu',
        '/acc[èe]s r[ée]serv[ée] aux propri[ée]taires/iu',
        '/r[ée]serv[ée] aux bailleurs/iu',
        '/compte admin/iu',
        '/utilisateur admin/iu',
        '/r[ôo]le utilisateur/iu',
    ];

    private const array STATUS_FALLBACKS = [
        400 => 'Requête invalide. Vérifiez les informations saisies et réessayez.',
        401 => 'Vous devez être connecté pour effectuer cette action.',
        403 => "Vous n'avez pas l'autorisation d'effectuer cette action.",
        404 => 'Ce service est temporairement indisponible. Veuillez réessayer plus tard.',
        408 => 'Le serveur a mis trop de temps à répondre. Veuillez réessayer.',
        409 => 'Cette action est en conflit avec une donnée existante.',
        413 => 'Le fichier ou la requête est trop volumineux.',
        419 => 'Votre session a expiré. Reconnectez-vous puis réessayez.',
        422 => 'Certaines informations sont invalides. Vérifiez le formulaire.',
        423 => 'Cette ressource est temporairement verrouillée.',
        429 => 'Trop de tentatives. Patientez quelques instants avant de réessayer.',
        500 => "Une erreur inattendue s'est produite. Notre équipe a été notifiée.",
        502 => 'Le serveur est momentanément injoignable. Réessayez dans un instant.',
        503 => 'Le service est temporairement indisponible. Réessayez plus tard.',
        504 => 'Le serveur met trop de temps à répondre. Réessayez dans un instant.',
    ];

    public static function isSensitive(string $message): bool
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return true;
        }

        foreach (self::UNSAFE_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed) === 1) {
                return true;
            }
        }

        return array_any(self::LEAKY_AUTH_PATTERNS, fn ($pattern) => preg_match($pattern, $trimmed) === 1);
    }

    public static function fallbackForStatus(int $status): string
    {
        return self::STATUS_FALLBACKS[$status] ?? 'Une erreur est survenue. Veuillez réessayer.';
    }

    public static function sanitize(string $message, int $status = 500, ?string $code = null): string
    {
        $trimmed = trim($message);

        if ($trimmed === '' || self::isSensitive($trimmed)) {
            return self::fallbackForStatus($status);
        }

        return $trimmed;
    }

    /**
     * Build a canonical API envelope {message, code, hint?, errors?, retry_after?}.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function envelope(string $message, ?string $code = null, int $status = 400, ?string $hint = null, ?array $errors = null, array $extra = []): array
    {
        $safeMessage = self::sanitize($message, $status, $code);
        $payload = ['message' => $safeMessage];

        if ($code !== null && $code !== '') {
            $payload['code'] = $code;
        }

        if ($hint !== null && trim($hint) !== '' && $hint !== $safeMessage && !self::isSensitive($hint)) {
            $payload['hint'] = trim($hint);
        }

        if ($errors !== null) {
            $filtered = [];
            foreach ($errors as $field => $messages) {
                $list = is_array($messages) ? $messages : [$messages];
                $safeList = [];
                foreach ($list as $m) {
                    if (is_string($m) && trim($m) !== '' && !self::isSensitive($m)) {
                        $safeList[] = trim($m);
                    }
                }
                if ($safeList !== []) {
                    $filtered[$field] = $safeList;
                }
            }
            if ($filtered !== []) {
                $payload['errors'] = $filtered;
            } elseif ($errors !== []) {
                // All messages were sensitive — surface a generic fallback instead of empty errors.
                $payload['errors'] = [];
            }
        }

        foreach ($extra as $k => $v) {
            $payload[$k] = $v;
        }

        return $payload;
    }
}

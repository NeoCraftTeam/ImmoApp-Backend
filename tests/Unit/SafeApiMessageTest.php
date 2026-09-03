<?php

declare(strict_types=1);

use App\Support\SafeApiMessage;

describe('isSensitive', function (): void {
    it('flags backend-internal messages as sensitive', function (string $message): void {
        expect(SafeApiMessage::isSensitive($message))->toBeTrue();
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'sql state' => 'SQLSTATE[42P01]: Undefined table: 7 ERROR',
        'not found' => 'Route [login] not defined.',
        'route keyword' => 'The route api/v1/ads could not be resolved',
        'controller keyword' => 'Unresolved binding in the controller layer',
        'namespace keyword' => 'Target namespace App\\Http is not instantiable',
        'undefined method' => 'Call to undefined method Foo::bar()',
        'stack trace' => 'Trace: #0 /var/www/app.php(12)',
        'url leak' => 'Webhook https://api.example.com/hook returned 500',
        'localhost leak' => 'Connection refused on 127.0.0.1:5432',
        'env leak' => 'Could not read .env configuration',
        'php keyword' => 'php artisan tinker crashed',
        'laravel keyword' => 'Laravel encountered a fatal error',
        'meilisearch keyword' => 'Meilisearch index is unreachable',
        'postgres keyword' => 'Postgres connection timed out',
        'leaky auth owner' => 'Accès réservé aux propriétaires',
        'leaky auth admin panel' => 'Panneau administrateur réservé aux admins',
        'env var name' => 'Configuration manquante : ORS_API_KEY',
        'env var name in sentence' => 'Ajoutez STRIPE_WEBHOOK_SECRET puis relancez',
    ]);

    it('treats user-facing French messages as safe', function (string $message): void {
        expect(SafeApiMessage::isSensitive($message))->toBeFalse();
    })->with([
        'bad credentials' => 'Identifiants incorrects',
        'slot unavailable' => "Ce créneau n'est pas disponible.",
        'status updated' => 'Statut mis à jour: Disponible',
        'rate limited' => 'Trop de tentatives. Patientez quelques instants.',
        'success' => 'Visite confirmée avec succès.',
        'french caps without underscore' => 'ATTENTION : votre dossier est incomplet.',
        'acronym in caps' => 'Votre document PDF a bien été reçu.',
    ]);
});

describe('fallbackForStatus', function (): void {
    it('returns the mapped fallback for known statuses', function (): void {
        expect(SafeApiMessage::fallbackForStatus(401))
            ->toBe('Vous devez être connecté pour effectuer cette action.')
            ->and(SafeApiMessage::fallbackForStatus(429))
            ->toBe('Trop de tentatives. Patientez quelques instants avant de réessayer.');
    });

    it('returns a generic fallback for unmapped statuses', function (): void {
        expect(SafeApiMessage::fallbackForStatus(418))
            ->toBe('Une erreur est survenue. Veuillez réessayer.');
    });
});

describe('sanitize', function (): void {
    it('passes through a safe message trimmed', function (): void {
        expect(SafeApiMessage::sanitize('  Identifiants incorrects  ', 401))
            ->toBe('Identifiants incorrects');
    });

    it('replaces a sensitive message with the status fallback', function (): void {
        expect(SafeApiMessage::sanitize('SQLSTATE[42P01] boom', 500))
            ->toBe("Une erreur inattendue s'est produite. Notre équipe a été notifiée.");
    });

    it('replaces an empty message with the status fallback', function (): void {
        expect(SafeApiMessage::sanitize('   ', 400))
            ->toBe('Requête invalide. Vérifiez les informations saisies et réessayez.');
    });
});

describe('envelope', function (): void {
    it('builds message and code for a safe message', function (): void {
        expect(SafeApiMessage::envelope('Identifiants incorrects', 'INVALID_CREDENTIALS', 401))
            ->toBe([
                'message' => 'Identifiants incorrects',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    });

    it('sanitizes a sensitive message but keeps the code', function (): void {
        expect(SafeApiMessage::envelope('SQLSTATE[42P01] boom', 'DB_ERROR', 500))
            ->toBe([
                'message' => "Une erreur inattendue s'est produite. Notre équipe a été notifiée.",
                'code' => 'DB_ERROR',
            ]);
    });

    it('omits an empty code', function (): void {
        expect(SafeApiMessage::envelope('Requête traitée', '', 200))
            ->toBe(['message' => 'Requête traitée']);
    });

    it('keeps a safe, distinct hint but drops a sensitive or duplicate one', function (): void {
        expect(SafeApiMessage::envelope('Créneau indisponible', 'SLOT_NOT_AVAILABLE', 409, hint: 'Choisissez un autre horaire.'))
            ->toBe([
                'message' => 'Créneau indisponible',
                'code' => 'SLOT_NOT_AVAILABLE',
                'hint' => 'Choisissez un autre horaire.',
            ]);

        expect(SafeApiMessage::envelope('Créneau indisponible', 'SLOT_NOT_AVAILABLE', 409, hint: 'SQLSTATE boom'))
            ->not->toHaveKey('hint');

        expect(SafeApiMessage::envelope('Créneau indisponible', 'SLOT_NOT_AVAILABLE', 409, hint: 'Créneau indisponible'))
            ->not->toHaveKey('hint');
    });

    it('filters sensitive entries out of the errors bag', function (): void {
        $payload = SafeApiMessage::envelope('Champs invalides', 'VALIDATION', 422, errors: [
            'email' => ['Ce champ est requis.'],
            'debug' => ['SQLSTATE[42P01] boom'],
        ]);

        expect($payload['errors'])->toBe(['email' => ['Ce champ est requis.']]);
    });

    it('surfaces an empty errors bag when every entry was sensitive', function (): void {
        $payload = SafeApiMessage::envelope('Champs invalides', 'VALIDATION', 422, errors: [
            'debug' => ['SQLSTATE[42P01] boom'],
        ]);

        expect($payload['errors'])->toBe([]);
    });

    it('merges extra fields such as retry_after', function (): void {
        expect(SafeApiMessage::envelope('Trop de tentatives', 'RATE_LIMITED', 429, extra: ['retry_after' => 30]))
            ->toBe([
                'message' => 'Trop de tentatives',
                'code' => 'RATE_LIMITED',
                'retry_after' => 30,
            ]);
    });
});

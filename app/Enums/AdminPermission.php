<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Granular admin permissions that gate access to specific Filament resources / actions.
 *
 * Each permission belongs to a logical "domain" (group label) so the permissions UI
 * can render them grouped (e.g. Newsletter, Modération, Finance).
 *
 * A user with `is_super_admin = true` (or `admin_permissions = NULL`) bypasses these
 * checks entirely — for backward compatibility with existing administrators.
 */
enum AdminPermission: string implements HasLabel
{
    // ── Users / Audience ─────────────────────────────────────────────
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case UsersBan = 'users.ban';

    // ── Modération annonces ─────────────────────────────────────────
    case AdsView = 'ads.view';
    case AdsModerate = 'ads.moderate';
    case AdReportsManage = 'ad_reports.manage';

    // ── Finance / Paiements ─────────────────────────────────────────
    case PaymentsView = 'payments.view';
    case PaymentsRefund = 'payments.refund';
    case CreditsManage = 'credits.manage';
    case SubscriptionsManage = 'subscriptions.manage';

    // ── Marketing / Newsletter ──────────────────────────────────────
    case NewsletterView = 'newsletter.view';
    case NewsletterSend = 'newsletter.send';
    case NewsletterSubscribersManage = 'newsletter.subscribers.manage';

    // ── Contenu ─────────────────────────────────────────────────────
    case CitiesManage = 'cities.manage';
    case AdTypesManage = 'ad_types.manage';
    case PropertyAttributesManage = 'property_attributes.manage';
    case PromoCodesManage = 'promo_codes.manage';
    case SurveysManage = 'surveys.manage';
    case ReviewsManage = 'reviews.manage';

    // ── Système ─────────────────────────────────────────────────────
    case SettingsAccess = 'settings.access';
    case ActivityLogsView = 'activity_logs.view';
    case JobsMonitor = 'jobs.monitor';
    case AcquisitionView = 'acquisition.view';
    case PermissionsManage = 'permissions.manage';

    public function getLabel(): string
    {
        return match ($this) {
            self::UsersView => 'Voir les utilisateurs',
            self::UsersManage => 'Gérer les utilisateurs',
            self::UsersBan => 'Bannir / réactiver les comptes',

            self::AdsView => 'Voir les annonces',
            self::AdsModerate => 'Modérer les annonces (approuver / masquer)',
            self::AdReportsManage => 'Traiter les signalements',

            self::PaymentsView => 'Voir les paiements',
            self::PaymentsRefund => 'Émettre des remboursements',
            self::CreditsManage => 'Gérer les crédits & transactions',
            self::SubscriptionsManage => 'Gérer les abonnements',

            self::NewsletterView => 'Voir les campagnes & abonnés',
            self::NewsletterSend => 'Envoyer des campagnes newsletter',
            self::NewsletterSubscribersManage => 'Gérer les abonnés à la newsletter',

            self::CitiesManage => 'Gérer villes & quartiers',
            self::AdTypesManage => 'Gérer les types d\'annonces',
            self::PropertyAttributesManage => 'Gérer les attributs de propriétés',
            self::PromoCodesManage => 'Gérer les codes promo',
            self::SurveysManage => 'Gérer les sondages',
            self::ReviewsManage => 'Modérer les avis',

            self::SettingsAccess => 'Accéder aux réglages système',
            self::ActivityLogsView => 'Consulter le journal de sécurité',
            self::JobsMonitor => 'Superviser les jobs en file',
            self::AcquisitionView => 'Consulter l\'analytique d\'acquisition',
            self::PermissionsManage => 'Gérer les rôles & permissions',
        };
    }

    /**
     * Logical grouping for the permissions UI.
     */
    public function getGroup(): string
    {
        return match ($this) {
            self::UsersView, self::UsersManage, self::UsersBan => 'Utilisateurs',

            self::AdsView, self::AdsModerate, self::AdReportsManage => 'Annonces & modération',

            self::PaymentsView, self::PaymentsRefund, self::CreditsManage,
            self::SubscriptionsManage => 'Finance',

            self::NewsletterView, self::NewsletterSend,
            self::NewsletterSubscribersManage => 'Marketing / Newsletter',

            self::CitiesManage, self::AdTypesManage, self::PropertyAttributesManage,
            self::PromoCodesManage, self::SurveysManage, self::ReviewsManage => 'Contenu',

            self::SettingsAccess, self::ActivityLogsView, self::JobsMonitor,
            self::AcquisitionView, self::PermissionsManage => 'Système',
        };
    }

    /**
     * Permissions baseline granted to every administrator (read-only safe).
     *
     * @return list<self>
     */
    public static function readOnlyDefaults(): array
    {
        return [
            self::UsersView,
            self::AdsView,
            self::PaymentsView,
            self::NewsletterView,
            self::ActivityLogsView,
        ];
    }

    /**
     * Preset for the "Marketing / Newsletter editor" persona.
     *
     * @return list<self>
     */
    public static function newsletterEditorPreset(): array
    {
        return [
            self::UsersView,
            self::NewsletterView,
            self::NewsletterSend,
            self::NewsletterSubscribersManage,
        ];
    }

    /**
     * Preset for the "Modérateur" persona.
     *
     * @return list<self>
     */
    public static function moderatorPreset(): array
    {
        return [
            self::UsersView,
            self::AdsView,
            self::AdsModerate,
            self::AdReportsManage,
            self::ReviewsManage,
        ];
    }

    /**
     * Group permissions by their `getGroup()` label.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $grouped[$case->getGroup()][] = $case;
        }

        return $grouped;
    }
}

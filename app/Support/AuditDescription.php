<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Ad;
use App\Models\AdReport;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PromoCode;
use App\Models\PropertyAttribute;
use App\Models\PropertyAttributeCategory;
use App\Models\Quarter;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Survey;
use App\Models\UnlockedAd;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Resolve a human-readable French sentence describing an admin action.
 *
 * Used by the Filament admin journal table, infolist and admin notification emails.
 * The resolver inspects the activity's `event`, `subject_type`, and `properties`
 * (attributes/old/action) to produce a single canonical description such as:
 *
 *   "a banni l'utilisateur jean@example.com"
 *   "a approuvé l'annonce « Bel appartement à Bonapriso »"
 *   "a modifié les paramètres de la plateforme"
 */
final class AuditDescription
{
    /**
     * Map subject_type FQCN → human French label.
     *
     * @var array<class-string, string>
     */
    public const array ENTITY_LABELS = [
        Ad::class => 'Annonce',
        User::class => 'Utilisateur',
        Agency::class => 'Agence',
        City::class => 'Ville',
        Quarter::class => 'Quartier',
        AdType::class => "Type d'annonce",
        Review::class => 'Avis',
        Payment::class => 'Paiement',
        Refund::class => 'Remboursement',
        Subscription::class => 'Abonnement',
        SubscriptionPlan::class => "Plan d'abonnement",
        PointPackage::class => 'Pack de crédits',
        UnlockedAd::class => 'Déblocage',
        PropertyAttribute::class => 'Attribut',
        PropertyAttributeCategory::class => "Catégorie d'attribut",
        Setting::class => 'Paramètre',
        AdReport::class => 'Signalement',
        PromoCode::class => 'Code promo',
        Survey::class => 'Sondage',
        NewsletterCampaign::class => 'Campagne newsletter',
        NewsletterSubscriber::class => 'Abonné newsletter',
    ];

    /**
     * Build a one-sentence French description from an Activity row.
     */
    public static function forActivity(Activity $activity): string
    {
        if ($activity->log_name === 'security') {
            return self::securityDescription($activity);
        }

        if ($activity->log_name === 'settings') {
            return $activity->description ?: 'a modifié les paramètres de la plateforme';
        }

        return self::adminActionDescription($activity);
    }

    /**
     * Build a short label for the action (e.g. "Bannissement", "Approbation").
     */
    public static function actionLabel(Activity $activity): string
    {
        if ($activity->log_name === 'security') {
            $action = (string) $activity->properties->get('action', $activity->event ?? '');

            return match ($action) {
                'login' => 'Connexion',
                'logout' => 'Déconnexion',
                'login_failed' => 'Échec connexion',
                'password_reset' => 'Réinit. mot de passe',
                'lockout' => 'Verrouillage',
                default => mb_ucfirst($action ?: '—'),
            };
        }

        $kind = self::detectActionKind($activity);

        return match ($kind) {
            'banned' => 'Bannissement',
            'unbanned' => 'Réactivation',
            'approved' => 'Approbation',
            'rejected' => 'Rejet',
            'archived' => 'Archivage',
            'restored' => 'Restauration',
            'force_deleted' => 'Suppression définitive',
            'deleted' => 'Suppression',
            'created' => 'Création',
            'updated' => 'Modification',
            default => mb_ucfirst((string) ($activity->event ?? '—')),
        };
    }

    /**
     * Subject label used in lists ("a modifié l'annonce «  X  »").
     */
    public static function entityLabel(Activity $activity): string
    {
        $type = (string) $activity->subject_type;

        return self::ENTITY_LABELS[$type] ?? ($type !== '' ? class_basename($type) : '—');
    }

    private static function securityDescription(Activity $activity): string
    {
        $action = (string) $activity->properties->get('action', $activity->event ?? '');
        $email = (string) $activity->properties->get('email', '');
        $ip = (string) $activity->properties->get('ip', '');

        $subject = $email !== '' ? " sur le compte {$email}" : '';
        $context = $ip !== '' ? " (IP {$ip})" : '';

        return match ($action) {
            'login' => "s'est connecté à l'administration{$context}",
            'logout' => "s'est déconnecté{$context}",
            'login_failed' => "échec de connexion{$subject}{$context}",
            'lockout' => "compte verrouillé après plusieurs tentatives{$subject}",
            'password_reset' => "a réinitialisé le mot de passe{$subject}",
            default => $activity->description ?: 'événement de sécurité',
        };
    }

    private static function adminActionDescription(Activity $activity): string
    {
        $kind = self::detectActionKind($activity);
        $entity = self::entityLabel($activity);
        $subjectName = self::subjectName($activity);
        $entityWithName = $subjectName !== '' ? "{$entity} « {$subjectName} »" : $entity;
        $entityLower = mb_strtolower($entity);
        $entityWithNameLower = $subjectName !== '' ? "{$entityLower} « {$subjectName} »" : $entityLower;

        return match ($kind) {
            'banned' => "a banni l'{$entityWithNameLower}",
            'unbanned' => "a réactivé l'{$entityWithNameLower}",
            'approved' => "a approuvé l'{$entityWithNameLower}",
            'rejected' => "a rejeté l'{$entityWithNameLower}",
            'archived' => "a archivé {$entityWithNameLower}",
            'restored' => "a restauré {$entityWithNameLower}",
            'force_deleted' => "a définitivement supprimé {$entityWithNameLower}",
            'deleted' => "a supprimé {$entityWithNameLower}",
            'created' => "a créé {$entityWithNameLower}",
            'updated' => "a modifié {$entityWithNameLower}",
            default => $activity->description ?: ($entityWithNameLower !== '' ? "a interagi avec {$entityWithNameLower}" : 'activité enregistrée'),
        };
    }

    /**
     * Detect whether an `updated` event represents a domain-specific transition
     * (ban / approve / reject) so we can produce a more useful description.
     */
    private static function detectActionKind(Activity $activity): string
    {
        // Explicit hint set by the producer (e.g. tapActivity / activity()->log())
        $explicit = (string) $activity->properties->get('action', '');
        if ($explicit !== '' && in_array($explicit, ['banned', 'unbanned', 'approved', 'rejected', 'archived', 'restored'], true)) {
            return $explicit;
        }

        $event = (string) ($activity->event ?? '');
        $attributes = (array) $activity->properties->get('attributes', []);
        $old = (array) $activity->properties->get('old', []);

        if ($event !== 'updated') {
            return $event;
        }

        // User ban/unban via is_active flag
        if ($activity->subject_type === User::class && array_key_exists('is_active', $attributes)) {
            $newVal = (bool) ($attributes['is_active'] ?? null);

            return $newVal ? 'unbanned' : 'banned';
        }

        // Ad approval / rejection via status field
        if ($activity->subject_type === Ad::class && array_key_exists('status', $attributes)) {
            $newStatus = (string) ($attributes['status'] ?? '');

            return match ($newStatus) {
                'available' => 'approved',
                'declined' => 'rejected',
                default => 'updated',
            };
        }

        return 'updated';
    }

    private static function subjectName(Activity $activity): string
    {
        $subject = $activity->subject;

        if (!$subject) {
            return '';
        }

        // Best-effort extraction without forcing extra queries.
        foreach (['title', 'name', 'subject', 'code', 'email', 'fullname', 'firstname'] as $field) {
            $value = data_get($subject, $field);
            if (is_string($value) && $value !== '') {
                return mb_strlen($value) > 60 ? mb_substr($value, 0, 60).'…' : $value;
            }
        }

        $first = data_get($subject, 'firstname');
        $last = data_get($subject, 'lastname');
        if (is_string($first) && is_string($last) && $first !== '' && $last !== '') {
            return "{$first} {$last}";
        }

        return '';
    }
}

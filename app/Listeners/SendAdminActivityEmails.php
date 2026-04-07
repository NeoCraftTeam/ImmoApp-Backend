<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Mail\AdminActionPerformedMail;
use App\Models\Ad;
use App\Models\AdReport;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PropertyAttribute;
use App\Models\Quarter;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Notifications\AdminCrudAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * Listens for Spatie activity log events.
 * When an admin performs a CRUD action, this listener:
 *   1. Sends a confirmation email to the acting admin
 *   2. Sends a notification (mail + Filament DB + WebPush) to all other admins
 *
 * Runs via the queue so synchronous mail calls never block HTTP workers.
 */
class SendAdminActivityEmails implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * @var array<string, string>
     */
    private const array ENTITY_LABELS = [
        Ad::class => 'Annonce',
        User::class => 'Utilisateur',
        Agency::class => 'Agence',
        City::class => 'Ville',
        Quarter::class => 'Quartier',
        AdType::class => "Type d'annonce",
        Review::class => 'Avis',
        Payment::class => 'Paiement',
        Subscription::class => 'Abonnement',
        SubscriptionPlan::class => "Plan d'abonnement",
        PointPackage::class => 'Pack de crédits',
        AdReport::class => 'Signalement annonce',
        UnlockedAd::class => 'Déblocage',
        PropertyAttribute::class => 'Attribut',
        Setting::class => 'Paramètre',
    ];

    public function handle(Activity $activity): void
    {
        $causer = $activity->causer;

        if (!$causer instanceof User) {
            return;
        }

        if ($causer->role !== UserRole::ADMIN) {
            return;
        }

        // Security log events (login, logout, failed login, password reset) are
        // audit-trail-only — they must never trigger admin notification emails.
        if ($activity->log_name === 'security') {
            return;
        }

        $entityLabel = self::ENTITY_LABELS[$activity->subject_type] ?? ($activity->subject_type ? class_basename($activity->subject_type) : 'Entité');

        $subject = $activity->subject;
        $entityName = $this->resolveEntityName($subject, $activity);

        // Detect ad approval / rejection to use a more descriptive event label
        $newStatus = $activity->properties['attributes']['status'] ?? null;
        $isAdApproval = $activity->subject_type === Ad::class
            && $activity->event === 'updated'
            && $newStatus === AdStatus::AVAILABLE->value;
        $isAdRejection = $activity->subject_type === Ad::class
            && $activity->event === 'updated'
            && $newStatus === AdStatus::DECLINED->value;

        $event = match (true) {
            $isAdApproval => 'approved',
            $isAdRejection => 'rejected',
            default => $activity->event ?? 'updated',
        };

        $details = [
            'event' => $event,
            'entity' => $entityLabel,
            'entity_name' => $entityName,
            'description' => $activity->description ?? "{$entityLabel} modifié(e)",
            'changes' => $activity->properties->toArray(),
            'date' => $activity->created_at->format('d/m/Y à H:i:s'),
        ];

        // 1. Confirmation email to the acting admin
        // Skip for ad approval/rejection — the action speaks for itself
        if (!$isAdApproval && !$isAdRejection) {
            try {
                Mail::to($causer->email)->send(new AdminActionPerformedMail($causer, $details));
            } catch (\Throwable $e) {
                Log::error('Failed to send admin action confirmation email: '.$e->getMessage());
            }
        }

        // 2. Notify other admins (mail + Filament DB notification + WebPush)
        try {
            $otherAdmins = User::query()
                ->where('role', UserRole::ADMIN)
                ->where('id', '!=', $causer->id)
                ->whereNotNull('email')
                ->get();

            if ($otherAdmins->isNotEmpty()) {
                Notification::send($otherAdmins, new AdminCrudAction($causer, $details));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send admin action notifications: '.$e->getMessage());
        }
    }

    /**
     * Resolve a human-readable name for the subject entity.
     */
    private function resolveEntityName(mixed $subject, Activity $activity): string
    {
        if (!$subject) {
            return "ID: {$activity->subject_id}";
        }

        if (method_exists($subject, 'getKey')) {
            if (isset($subject->title)) {
                return $subject->title;
            }

            if (isset($subject->name)) {
                return $subject->name;
            }

            if (isset($subject->firstname, $subject->lastname)) {
                return $subject->firstname.' '.$subject->lastname;
            }

            if (isset($subject->key)) {
                return $subject->key;
            }
        }

        return "#{$activity->subject_id}";
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Mail\AdminActionPerformedMail;
use App\Models\User;
use App\Notifications\AdminCrudAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * Listens for Spatie activity log events.
 * When an admin performs a CRUD action, this listener:
 *   1. Sends a confirmation email to the acting admin
 *   2. Sends a notification (mail + Filament DB + WebPush) to all other admins
 */
class SendAdminActivityEmails
{
    /**
     * @var array<string, string>
     */
    private const array ENTITY_LABELS = [
        \App\Models\Ad::class => 'Annonce',
        \App\Models\User::class => 'Utilisateur',
        \App\Models\Agency::class => 'Agence',
        \App\Models\City::class => 'Ville',
        \App\Models\Quarter::class => 'Quartier',
        \App\Models\AdType::class => "Type d'annonce",
        \App\Models\Review::class => 'Avis',
        \App\Models\Payment::class => 'Paiement',
        \App\Models\Subscription::class => 'Abonnement',
        \App\Models\SubscriptionPlan::class => "Plan d'abonnement",
        \App\Models\PointPackage::class => 'Pack de crédits',
        \App\Models\UnlockedAd::class => 'Déblocage',
        \App\Models\PropertyAttribute::class => 'Attribut',
        \App\Models\Setting::class => 'Paramètre',
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

        $entityLabel = self::ENTITY_LABELS[$activity->subject_type] ?? ($activity->subject_type ? class_basename($activity->subject_type) : 'Entité');

        $subject = $activity->subject;
        $entityName = $this->resolveEntityName($subject, $activity);

        $details = [
            'event' => $activity->event ?? 'updated',
            'entity' => $entityLabel,
            'entity_name' => $entityName,
            'description' => $activity->description ?? "{$entityLabel} modifié(e)",
            'changes' => $activity->properties->toArray(),
            'date' => $activity->created_at->format('d/m/Y à H:i:s'),
        ];

        // 1. Confirmation email to the acting admin
        try {
            Mail::to($causer->email)->send(new AdminActionPerformedMail($causer, $details));
        } catch (\Throwable $e) {
            Log::error('Failed to send admin action confirmation email: '.$e->getMessage());
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

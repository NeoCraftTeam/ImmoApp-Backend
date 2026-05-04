<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminCrudAction;
use App\Support\AuditDescription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * Listens for Spatie activity log events.
 *
 * When an admin performs any logged action, this listener notifies *every*
 * admin (including the actor) via:
 *   - Filament database notification (bell)
 *   - Email (`AdminActionNotifyMail` template)
 *   - WebPush (when an admin has a subscription)
 *
 * Runs via the queue so synchronous mail calls never block HTTP workers.
 */
class SendAdminActivityEmails implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

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

        $details = [
            'event' => AuditDescription::actionLabel($activity),
            'entity' => AuditDescription::entityLabel($activity),
            'entity_name' => $this->resolveEntityName($activity->subject, $activity),
            'description' => AuditDescription::forActivity($activity),
            'changes' => $activity->properties->toArray(),
            'date' => $activity->created_at->format('d/m/Y à H:i:s'),
        ];

        // Notify *all* admins (including the actor). The notification template
        // greets the actor with "Vous avez …" and other admins with "X a …".
        try {
            $allAdmins = User::query()
                ->where('role', UserRole::ADMIN)
                ->whereNotNull('email')
                ->get();

            if ($allAdmins->isNotEmpty()) {
                Notification::send($allAdmins, new AdminCrudAction($causer, $details));
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
            foreach (['title', 'name', 'subject', 'code', 'email'] as $field) {
                $value = data_get($subject, $field);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            $first = data_get($subject, 'firstname');
            $last = data_get($subject, 'lastname');
            if (is_string($first) && is_string($last) && $first !== '' && $last !== '') {
                return "{$first} {$last}";
            }

            $key = data_get($subject, 'key');
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }

        return "#{$activity->subject_id}";
    }
}

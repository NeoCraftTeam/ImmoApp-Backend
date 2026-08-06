<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\UserRole;
use App\Mail\Concerns\HasLocale;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notification when a passkey is added or removed.
 *
 * Uses the teal (owner) layout for agents/admins and the primary (client) layout for customers.
 */
final class PasskeyNotificationMail extends Mailable implements ShouldQueue
{
    use HasLocale;
    use Queueable, SerializesModels;

    /**
     * @param  'added'|'removed'  $action  What happened to the passkey
     * @param  string  $deviceName  Alias/device name of the passkey
     * @param  string  $ipAddress  IP address of the request
     * @param  string  $userAgent  Browser/device user-agent string
     */
    public function __construct(
        public User $user,
        public string $action,
        public string $deviceName,
        public string $ipAddress = '',
        public string $userAgent = '',
    ) {
        $this->applyRecipientLocale();

        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        $verb = $this->action === 'added' ? 'ajoutée à' : 'supprimée de';

        return new Envelope(
            subject: "Passkey {$verb} votre compte — ".config('app.name'),
        );
    }

    public function content(): Content
    {
        $isOwner = in_array($this->user->role, [UserRole::AGENT, UserRole::ADMIN], true);
        $layout = $isOwner ? 'emails.owner-layout' : 'emails.layout';

        return new Content(
            view: 'emails.passkey-notification',
            with: [
                'emailLayout' => $layout,
                'isOwner' => $isOwner,
                'userName' => $this->user->firstname ?? 'Utilisateur',
                'actionLabel' => $this->action === 'added' ? 'ajoutée' : 'supprimée',
                'actionVerb' => $this->action === 'added'
                    ? 'a été ajoutée à votre compte'
                    : 'a été supprimée de votre compte',
                'deviceName' => $this->deviceName ?: 'Appareil inconnu',
                'ipAddress' => $this->ipAddress ?: 'Non disponible',
                'userAgent' => $this->userAgent ?: 'Non disponible',
                'timestamp' => now()->utc()->format('d/m/Y \à H:i').' (UTC)',
                'securityUrl' => $isOwner
                    ? rtrim((string) config('app.frontend_url', config('app.url')), '/').'/owner/security'
                    : rtrim((string) config('app.frontend_url', config('app.url')), '/').'/settings/security',
            ],
        );
    }

    protected function resolveRecipientUser(): ?User // @phpstan-ignore return.unusedType
    {
        return $this->user;
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\AdminActionNotifyMail;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AdminCrudAction extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{event: string, entity: string, entity_name: string, description: string, changes: array<string, mixed>, date: string}  $details
     */
    public function __construct(
        public User $actor,
        public array $details,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): Mailable
    {
        return new AdminActionNotifyMail($this->actor, $notifiable, $this->details);
    }

    /**
     * Format the notification for the Filament database notification bell.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $eventIcons = [
            'created' => 'heroicon-o-plus-circle',
            'updated' => 'heroicon-o-pencil-square',
            'deleted' => 'heroicon-o-trash',
            'approved' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
        ];

        $eventColors = [
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'danger',
            'approved' => 'success',
            'rejected' => 'danger',
        ];

        $event = $this->details['event'];

        return FilamentNotification::make()
            ->title("{$this->details['entity']} — {$this->details['description']}")
            ->body("Par {$this->actor->firstname} {$this->actor->lastname} le {$this->details['date']}")
            ->icon($eventIcons[$event] ?? 'heroicon-o-information-circle')
            ->color($eventColors[$event] ?? 'gray')
            ->getDatabaseMessage();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Action admin - KeyHome')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("{$this->actor->firstname} : {$this->details['description']} ({$this->details['entity']} \"{$this->details['entity_name']}\")")
            ->tag('admin-action-'.$this->details['event'].'-'.now()->timestamp)
            ->data(['url' => config('app.url').'/admin']);
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Admin\Resources\PendingAds\PendingAdResource;
use App\Mail\NewAdSubmissionMail;
use App\Models\Ad;
use App\Support\PanelUrl;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewAdPending extends Notification implements ShouldQueue
{
    private function resolveAdminPendingAdsUrl(): string
    {
        try {
            return PendingAdResource::getUrl(panel: 'admin');
        } catch (\Throwable) {
            return PanelUrl::for('admin', 'pending-ads');
        }
    }

    use Queueable;

    public function __construct(
        public Ad $ad,
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
        return (new NewAdSubmissionMail($this->ad))->to($notifiable->email);
    }

    /**
     * Format the notification for the Filament database notification bell.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $url = $this->resolveAdminPendingAdsUrl();

        return FilamentNotification::make()
            ->title('Nouvelle annonce en attente')
            ->body("L'annonce \"{$this->ad->title}\" (par ".($this->ad->user->fullname ?? 'Inconnu').') nécessite votre validation.')
            ->warning()
            ->icon('heroicon-o-home-modern')
            ->actions([
                FilamentAction::make('review')
                    ->label('Examiner')
                    ->url($url)
                    ->color('warning')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $authorName = $this->ad->user->fullname ?? 'Inconnu';

        return (new WebPushMessage)
            ->title('Nouvelle annonce en attente - KeyHome')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("L'annonce \"{$this->ad->title}\" (par {$authorName}) nécessite votre validation.")
            ->tag('ad-pending-'.$this->ad->id)
            ->data(['url' => $this->resolveAdminPendingAdsUrl()]);
    }
}

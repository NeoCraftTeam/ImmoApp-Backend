<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Admin\Resources\AdReports\AdReportResource;
use App\Mail\NewAdReportMail;
use App\Models\AdReport;
use App\Support\PanelUrl;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewAdListingReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AdReport $report,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->hasValidEmail($notifiable)) {
            $channels[] = 'mail';
        }

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): Mailable
    {
        return new NewAdReportMail($this->report, $notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        $url = $this->resolveReviewUrl();

        return FilamentNotification::make()
            ->title('Nouveau signalement annonce')
            ->body("Annonce \"{$this->report->ad->title}\" signalee par {$this->report->reporter->fullname}.")
            ->warning()
            ->icon('heroicon-o-flag')
            ->actions([
                FilamentAction::make('review')
                    ->label('Traiter')
                    ->url($url)
                    ->color('warning')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nouveau signalement - KeyHome')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("Annonce \"{$this->report->ad->title}\" signalee. Action admin requise.")
            ->tag('ad-report-'.$this->report->id)
            ->data(['url' => $this->resolveReviewUrl()]);
    }

    private function hasValidEmail(object $notifiable): bool
    {
        $email = data_get($notifiable, 'email');

        return is_string($email)
            && filled($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function resolveReviewUrl(): string
    {
        $fallback = PanelUrl::for('admin', "ad-reports/{$this->report->id}/edit");

        try {
            return AdReportResource::getUrl('edit', ['record' => $this->report->id], panel: 'admin');
        } catch (\Throwable) {
            return $fallback;
        }
    }
}

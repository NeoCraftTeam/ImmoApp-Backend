<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Admin\Resources\Surveys\SurveyResource;
use App\Models\AnonymousSurveyResponse;
use App\Models\Survey;
use App\Support\PanelUrl;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewAnonymousSurveyResponse extends Notification implements ShouldQueue
{
    private function resolveAdminSurveyUrl(): string
    {
        try {
            return SurveyResource::getUrl('view', ['record' => $this->survey->id], panel: 'admin');
        } catch (\Throwable) {
            return PanelUrl::for('admin', "surveys/{$this->survey->id}");
        }
    }

    use Queueable;

    public function __construct(
        public readonly Survey $survey,
        public readonly AnonymousSurveyResponse $response,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (!app()->environment('production', 'staging', 'development')) {
            $channels[] = 'mail';

            if ($notifiable->pushSubscriptions()->exists()) {
                $channels[] = WebPushChannel::class;
            }
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->response->loadMissing(['answers.question']);

        /** @var array<int, array{question: string, answer: string}> $formattedAnswers */
        $formattedAnswers = $this->response->answers->map(function ($answer): array {
            $type = $answer->question->type ?? 'text';
            $raw = $answer->answer;

            if (in_array($type, ['checkbox', 'multiple_choice'], true)) {
                $decoded = json_decode($raw, true);
                $formatted = is_array($decoded) ? implode(', ', $decoded) : $raw;
            } elseif ($type === 'rating') {
                $stars = (int) $raw;
                $formatted = str_repeat('★', $stars).str_repeat('☆', 5 - $stars).' ('.$raw.'/5)';
            } else {
                $formatted = $raw;
            }

            return [
                'question' => $answer->question->text ?? '—',
                'answer' => $formatted,
            ];
        })->all();

        $adminUrl = $this->resolveAdminSurveyUrl();

        return (new MailMessage)
            ->subject('KeyHome — Nouvelle réponse anonyme : «'.$this->survey->title.'»')
            ->view('emails.survey-admin-notification', [
                'surveyTitle' => $this->survey->title,
                'respondentName' => 'Répondant anonyme',
                'respondentEmail' => '— (réponse anonyme)',
                'formattedAnswers' => $formattedAnswers,
                'adminUrl' => $adminUrl,
            ]);
    }

    /**
     * Format the notification for the Filament database notification bell.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Nouveau sondage anonyme reçu')
            ->body('«'.$this->survey->title.'» — '.now()->format('d/m/Y à H:i'))
            ->icon('heroicon-o-clipboard-document-list')
            ->color('warning')
            ->actions([
                FilamentAction::make('view')
                    ->label('Voir les réponses')
                    ->url($this->resolveAdminSurveyUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nouveau sondage — KeyHome')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body('Nouvelle réponse anonyme reçue pour «'.$this->survey->title.'»')
            ->tag('survey-response-'.$this->survey->id)
            ->data(['url' => $this->resolveAdminSurveyUrl()]);
    }
}

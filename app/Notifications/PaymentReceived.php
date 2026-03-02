<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public string $description = ''
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Paiement reçu - KeyHome')
            ->greeting('Bonjour '.$notifiable->name.' !')
            ->line('Votre paiement a été reçu avec succès.')
            ->line('Montant: '.number_format((float) $this->payment->amount, 0, ',', ' ').' FCFA')
            ->line('Référence: '.$this->payment->transaction_id)
            ->when($this->description !== '', fn ($mail) => $mail->line($this->description))
            ->action('Voir mes paiements', config('app.frontend_url').'/profile/payments')
            ->line('Merci de votre confiance !');
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Paiement reçu - KeyHome')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body('Paiement de '.number_format((float) $this->payment->amount, 0, ',', ' ').' FCFA reçu')
            ->tag('payment-'.$this->payment->id)
            ->data(['url' => config('app.frontend_url').'/profile/payments']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_received',
            'title' => 'Paiement reçu',
            'message' => 'Paiement de '.number_format((float) $this->payment->amount, 0, ',', ' ').' FCFA reçu',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'transaction_id' => $this->payment->transaction_id,
            'description' => $this->description,
        ];
    }
}

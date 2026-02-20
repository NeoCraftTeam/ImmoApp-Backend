<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return ['database', 'mail'];
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

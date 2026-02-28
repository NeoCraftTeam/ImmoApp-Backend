<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdUnlockConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $logoBase64 = '';

    public function __construct(
        public User $user,
        public Ad $ad,
        public Payment $payment,
    ) {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }

        $logoPath = public_path('images/keyhomelogo_transparent.png');
        if (file_exists($logoPath)) {
            $this->logoBase64 = base64_encode((string) file_get_contents($logoPath));
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Coordonnées débloquées — '.$this->ad->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ad-unlock-confirmation',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('receipts.unlock-payment', [
            'user' => $this->user,
            'ad' => $this->ad,
            'payment' => $this->payment,
            'logoBase64' => $this->logoBase64,
        ])->setPaper('a4', 'portrait');

        return [
            Attachment::fromData(fn () => $pdf->output(), 'recu-paiement-'.$this->payment->transaction_id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CardAddedMail extends Mailable implements ShouldBeUnique, ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public string $uniqueId;

    public function __construct(
        public readonly User $user,
        public readonly string $cardBrand,
        public readonly string $cardLast4,
    ) {
        $this->uniqueId = "card-added-{$user->id}-{$cardLast4}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Une carte bancaire a été ajoutée à votre compte '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.card-added',
        );
    }
}

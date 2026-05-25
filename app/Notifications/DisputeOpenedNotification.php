<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\DisputeOpenedMail;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class DisputeOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Dispute $dispute) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        /** @var User $notifiable */
        return new DisputeOpenedMail($this->dispute, $notifiable)
            ->to($notifiable->email, $notifiable->firstname.' '.$notifiable->lastname);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_opened',
            'dispute_id' => $this->dispute->id,
            'reference' => $this->dispute->reference,
            'title' => $this->dispute->title,
            'message' => "Un litige « {$this->dispute->reference} » a été ouvert : {$this->dispute->title}.",
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\DisputeStatus;
use App\Mail\DisputeStatusChangedMail;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class DisputeStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Dispute $dispute,
        public DisputeStatus $previousStatus,
        public DisputeStatus $newStatus,
    ) {}

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
        return new DisputeStatusChangedMail($this->dispute, $notifiable)
            ->to($notifiable->email, $notifiable->firstname.' '.$notifiable->lastname);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_status_changed',
            'dispute_id' => $this->dispute->id,
            'reference' => $this->dispute->reference,
            'previous_status' => $this->previousStatus->value,
            'new_status' => $this->newStatus->value,
            'message' => "Le litige « {$this->dispute->reference} » est désormais : {$this->newStatus->getLabel()}.",
        ];
    }
}

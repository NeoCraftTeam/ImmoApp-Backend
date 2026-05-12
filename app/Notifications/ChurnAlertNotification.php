<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChurnAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $landlord,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'churn_alert',
            'user_id' => $this->landlord->id,
            'user_name' => "{$this->landlord->firstname} {$this->landlord->lastname}",
            'message' => "{$this->landlord->firstname} {$this->landlord->lastname} a supprimé des annonces récemment — risque de churn.",
        ];
    }
}

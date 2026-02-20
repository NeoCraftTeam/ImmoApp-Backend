<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ad;
use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Review $review,
        public Ad $ad
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
        $rating = (int) $this->review->rating;
        $stars = str_repeat('★', $rating).str_repeat('☆', 5 - $rating);

        return (new MailMessage)
            ->subject('Nouvel avis sur votre annonce - KeyHome')
            ->greeting('Bonjour '.$notifiable->name.' !')
            ->line('Vous avez reçu un nouvel avis sur votre annonce "'.$this->ad->title.'".')
            ->line('Note: '.$stars.' ('.$rating.'/5)')
            ->when($this->review->comment, fn ($mail) => $mail->line('Commentaire: "'.$this->review->comment.'"'))
            ->action('Voir l\'avis', config('app.frontend_url').'/ads/'.$this->ad->slug)
            ->line('Merci d\'utiliser KeyHome !');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_review',
            'title' => 'Nouvel avis',
            'message' => 'Nouvel avis '.$this->review->rating.'/5 sur "'.$this->ad->title.'"',
            'review_id' => $this->review->id,
            'ad_id' => $this->ad->id,
            'ad_title' => $this->ad->title,
            'rating' => $this->review->rating,
            'reviewer_name' => $this->review->user?->fullname,
        ];
    }
}

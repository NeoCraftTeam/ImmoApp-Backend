<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Review;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GdprDataExportMail extends Mailable implements ShouldQueue
{
    use Concerns\HasUnsubscribeLinks;
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        if (app()->environment(['production', 'staging'])) {
            $this->onQueue('emails');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vos données personnelles — export RGPD',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gdpr-data-export',
            with: $this->withUnsubscribe([
                'authorName' => $this->user->firstname,
            ]),
        );
    }

    /** @return Attachment[] */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => json_encode($this->buildExportPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'keyhome-mes-donnees-'.now()->format('Y-m-d').'.json'
            )->withMime('application/json'),
        ];
    }

    /** @return array<string, mixed> */
    private function buildExportPayload(): array
    {
        $user = $this->user;

        $paymentsPayload = [];
        foreach ($user->payments()->get() as $payment) {
            if (!$payment instanceof Payment) {
                continue;
            }
            $paymentsPayload[] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'gateway' => $payment->gateway,
                'created_at' => $payment->created_at?->toIso8601String(),
            ];
        }

        $unlockedAdsPayload = [];
        foreach ($user->unlockedAds()->with('ad:id,title')->get() as $unlock) {
            if (!$unlock instanceof UnlockedAd) {
                continue;
            }
            $unlockedAdsPayload[] = [
                'ad_id' => $unlock->ad_id,
                'ad_title' => $unlock->ad?->title,
                'created_at' => $unlock->unlocked_at?->toIso8601String(),
            ];
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role->value,
                'type' => $user->type?->value,
                'city' => $user->city?->name,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at,
                'onboarding_completed_at' => $user->onboarding_completed_at?->toIso8601String(),
            ],
            'ads' => $user->ads()->with(['adType', 'quarter.city'])->get()
                ->map(fn (Ad $ad): array => [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'slug' => $ad->slug,
                    'status' => $ad->status->value,
                    'price' => $ad->price,
                    'created_at' => $ad->created_at?->toIso8601String(),
                ])->toArray(),
            'reviews' => $user->reviews()->get()
                ->map(fn (Review $review): array => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'ad_id' => $review->ad_id,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->toArray(),
            'payments' => $paymentsPayload,
            'unlocked_ads' => $unlockedAdsPayload,
            'point_transactions' => $user->pointTransactions()->get()
                ->map(fn (PointTransaction $pt): array => [
                    'type' => $pt->type->value,
                    'points' => $pt->points,
                    'description' => $pt->description,
                    'created_at' => $pt->created_at?->toIso8601String(),
                ])->toArray(),
            'email_preferences' => $user->emailPreference?->only([
                'ad_updates', 'search_alerts', 'subscription_updates',
                'survey_notifications', 'admin_notifications', 'welcome_emails',
                'engagement_emails', 'digest_emails',
            ]),
        ];
    }

    protected function resolveRecipientUser(): ?User
    {
        return $this->user;
    }

    protected function emailCategory(): string
    {
        return 'ad_updates';
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Mail\AdSubmissionConfirmationMail;
use App\Mail\NewAdSubmissionMail;
use App\Models\Ad;
use App\Models\User;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class AdObserver
{
    /**
     * Handle the Ad "created" event.
     */
    public function created(Ad $ad): void
    {
        // Auto-boost if agency has active subscription
        app(\App\Services\AdBoostService::class)->autoBoostIfEligible($ad);

        // Notify admins if status is PENDING
        if ($ad->status === AdStatus::PENDING) {
            // 1. Send confirmation to the author
            if ($ad->user) {
                try {
                    Mail::to($ad->user)->send(new AdSubmissionConfirmationMail($ad));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send ad confirmation email to {$ad->user->email}: ".$e->getMessage());
                }
            }

            // 2. Notify all admins
            $admins = User::where('role', UserRole::ADMIN)->get();
            foreach ($admins as $admin) {
                try {
                    Mail::to($admin)->send(new NewAdSubmissionMail($ad));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send admin notification email to {$admin->email}: ".$e->getMessage());
                }
            }

            // 3. Send Filament Database Notification to admins
            try {
                $url = '#';
                try {
                    $url = \App\Filament\Admin\Resources\Ads\AdResource::getUrl('edit', ['record' => $ad]);
                } catch (\Exception $e) {
                    // Fallback if route invalid (e.g. testing)
                }

                \Filament\Notifications\Notification::make()
                    ->title('Nouvelle annonce en attente')
                    ->body("L'annonce \"{$ad->title}\" (par ".($ad->user->fullname ?? 'Inconnu').') nécessite votre validation.')
                    ->warning()
                    ->icon('heroicon-o-home-modern')
                    ->actions([
                        NotificationAction::make('review')
                            ->label('Examiner')
                            ->url($url)
                            ->button()
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($admins);

            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send Filament database notification: '.$e->getMessage());
            }
        }
    }

    /**
     * Handle the Ad "updated" event.
     */
    public function updated(Ad $ad): void
    {
        //
    }

    /**
     * Handle the Ad "deleted" event.
     */
    public function deleted(Ad $ad): void
    {
        //
    }

    /**
     * Handle the Ad "restored" event.
     */
    public function restored(Ad $ad): void
    {
        //
    }

    /**
     * Handle the Ad "force deleted" event.
     */
    public function forceDeleted(Ad $ad): void
    {
        //
    }
}

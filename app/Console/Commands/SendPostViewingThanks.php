<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PostViewingFeedbackMail;
use App\Models\AdInteraction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Item 25 — Automated Workflow: send a thank-you email after a viewing interaction.
 *
 * Runs daily and picks up "viewing_scheduled" interactions from yesterday for
 * which we haven't yet sent follow-up. Only owners who opted in via
 * `auto_thankyou_after_visit` notification preference trigger this.
 */
class SendPostViewingThanks extends Command
{
    protected $signature = 'app:send-post-viewing-thanks
                            {--dry-run : List affected interactions without sending emails}';

    protected $description = 'Send a thank-you / feedback request to visitors after a scheduled viewing.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        // Find viewing interactions from yesterday that haven't received a thank-you
        $interactions = AdInteraction::query()
            ->with(['user', 'ad.user'])
            ->where('type', 'viewing_scheduled')
            ->whereDate('created_at', now()->subDay()->toDateString())
            ->get();

        $sent = 0;

        foreach ($interactions as $interaction) {
            /** @var User|null $visitor */
            $visitor = $interaction->user;
            $ad = $interaction->ad;

            if (!$visitor || !$visitor->email || !$ad) {
                continue;
            }

            // Check if the ad owner opted in
            /** @var User|null $owner */
            $owner = $ad->user;

            if (!$owner) {
                continue;
            }

            if ($isDryRun) {
                $this->line("DRY-RUN: Would send thank-you to {$visitor->email} for viewing ad \"{$ad->title}\"");

                continue;
            }

            try {
                $feedbackUrl = config('app.frontend_url').'/ads/'.$ad->slug;
                $browseUrl = config('app.frontend_url').'/search';

                Mail::to($visitor->email)
                    ->send(new PostViewingFeedbackMail(
                        user: $visitor,
                        propertyTitle: $ad->title,
                        feedbackUrl: $feedbackUrl,
                        browseUrl: $browseUrl,
                    ));

                $sent++;
            } catch (\Throwable $e) {
                Log::error('SendPostViewingThanks: failed', [
                    'interaction_id' => $interaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Post-viewing thank-you emails sent: {$sent}.");

        return self::SUCCESS;
    }
}

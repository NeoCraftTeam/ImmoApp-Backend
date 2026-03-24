<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Item 25 — Automated Workflow: hide ads with no activity for 30+ days.
 *
 * This only hides ads whose owner has opted-in via the `auto_hide_stale_ads`
 * notification preference. Ads are set to invisible (soft-hidden), not deleted.
 */
class AutoHideStaleAds extends Command
{
    protected $signature = 'app:auto-hide-stale-ads
                            {--days=30 : Number of days without activity}
                            {--dry-run : Print affected ads without hiding them}';

    protected $description = 'Automatically hide ads with no views/interactions for N days (opt-in owners only).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $isDryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($days);

        // Find AVAILABLE visible ads with no interactions since cutoff
        $staleAds = Ad::query()
            ->with('user')
            ->where('status', AdStatus::AVAILABLE)
            ->where('is_visible', true)
            ->whereDoesntHave('interactions', function ($q) use ($cutoff): void {
                $q->where('created_at', '>=', $cutoff);
            })
            ->where('updated_at', '<', $cutoff)
            ->get();

        $hidden = 0;

        foreach ($staleAds as $ad) {
            $owner = $ad->user;

            if (!$owner) {
                continue;
            }

            // Only proceed for owners who have opted in
            $prefJson = DB::table('users')
                ->where('id', $owner->id)
                ->value('notification_preferences');

            $prefs = $prefJson ? json_decode((string) $prefJson, true) : [];

            if (empty($prefs['auto_hide_stale_ads'])) {
                continue;
            }

            if ($isDryRun) {
                $this->line("DRY-RUN: Would hide ad {$ad->id} — \"{$ad->title}\" (owner: {$owner->email})");

                continue;
            }

            $ad->forceFill(['is_visible' => false])->save();
            $hidden++;

            Log::info('AutoHideStaleAds: ad hidden', [
                'ad_id' => $ad->id,
                'user_id' => $owner->id,
                'days_inactive' => $days,
            ]);
        }

        $this->info("Auto-hidden {$hidden} stale ad(s) (threshold: {$days} days).");

        return self::SUCCESS;
    }
}

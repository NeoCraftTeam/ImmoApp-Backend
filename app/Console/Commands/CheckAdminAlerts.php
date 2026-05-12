<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use App\Notifications\ChurnAlertNotification;
use App\Notifications\FraudAlertNotification;
use App\Notifications\InactiveLandlordNotification;
use App\Notifications\LowViewsAdNotification;
use App\Notifications\RevenueDropNotification;
use App\Services\AdminMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CheckAdminAlerts extends Command
{
    protected $signature = 'app:check-admin-alerts';

    protected $description = 'Check for admin alert conditions and send notifications';

    public function handle(AdminMetricsService $metricsService): int
    {
        $this->info('Checking admin alerts...');

        $this->checkInactiveLandlords();
        $this->checkLowViewAds();
        $this->checkFraudFlags();
        $this->checkChurnSignals();
        $this->checkRevenueDrop($metricsService);

        $this->info('Admin alerts check complete.');

        return self::SUCCESS;
    }

    private function checkInactiveLandlords(): void
    {
        $inactive = User::where('role', UserRole::AGENT)
            ->whereHas('ads')
            ->whereDoesntHave('ads', fn ($q) => $q->where('updated_at', '>=', now()->subDays(30)))
            ->get();

        $count = $inactive->count();
        $this->line("  Inactive landlords (30d): {$count}");

        foreach ($inactive as $landlord) {
            $landlord->notify(new InactiveLandlordNotification);
        }
    }

    private function checkLowViewAds(): void
    {
        $ads = Ad::where('status', AdStatus::AVAILABLE)
            ->where('created_at', '<=', now()->subDays(14))
            ->whereDoesntHave('interactions', fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)->where('created_at', '>=', now()->subDays(14)))
            ->with('user')
            ->get();

        $count = $ads->count();
        $this->line("  Low view ads (14d): {$count}");

        foreach ($ads as $ad) {
            if ($ad->user) {
                $ad->user->notify(new LowViewsAdNotification($ad));
            }
        }
    }

    private function checkFraudFlags(): void
    {
        $flaggedOwners = DB::table('ad_reports')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('owner_id, COUNT(*) as report_count')
            ->groupBy('owner_id')
            ->havingRaw('COUNT(*) >= 3')
            ->pluck('report_count', 'owner_id');

        $count = $flaggedOwners->count();
        $this->line("  Fraud-flagged owners: {$count}");

        if ($flaggedOwners->isNotEmpty()) {
            $admins = User::where('role', UserRole::ADMIN)->get();
            foreach ($flaggedOwners as $ownerId => $reportCount) {
                $owner = User::find($ownerId);
                if ($owner) {
                    Notification::send($admins, new FraudAlertNotification($owner, $reportCount));
                }
            }
        }
    }

    private function checkChurnSignals(): void
    {
        $churning = User::where('role', UserRole::AGENT)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('ad')
                ->whereColumn('ad.user_id', 'users.id')
                ->whereNotNull('ad.deleted_at')
                ->where('ad.deleted_at', '>=', now()->subDays(7)))
            ->get();

        $count = $churning->count();
        $this->line("  Churn-imminent landlords: {$count}");

        if ($churning->isNotEmpty()) {
            $admins = User::where('role', UserRole::ADMIN)->get();
            foreach ($churning as $landlord) {
                Notification::send($admins, new ChurnAlertNotification($landlord));
            }
        }
    }

    private function checkRevenueDrop(AdminMetricsService $metricsService): void
    {
        $alerts = $metricsService->checkAlerts();

        if ($alerts['revenue_declining']) {
            $this->line('  Revenue declining: YES — sending alert');
            $admins = User::where('role', UserRole::ADMIN)->get();
            Notification::send($admins, new RevenueDropNotification);
        } else {
            $this->line('  Revenue declining: NO');
        }
    }
}

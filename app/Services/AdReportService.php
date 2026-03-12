<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdReportStatus;
use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\AdReport;
use App\Models\User;
use App\Notifications\AdReportReceivedNotification;
use App\Notifications\NewAdListingReportNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdReportService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(User $reporter, Ad $ad, array $payload, Request $request): AdReport
    {
        if ($ad->user_id === $reporter->id) {
            throw ValidationException::withMessages([
                'reason' => 'Vous ne pouvez pas signaler votre propre annonce.',
            ]);
        }

        $openReportExists = AdReport::query()
            ->where('ad_id', $ad->id)
            ->where('reporter_id', $reporter->id)
            ->open()
            ->exists();

        if ($openReportExists) {
            throw ValidationException::withMessages([
                'reason' => 'Vous avez deja un signalement en cours pour cette annonce.',
            ]);
        }

        /** @var AdReport $report */
        $report = DB::transaction(fn (): AdReport => AdReport::query()->create([
            'ad_id' => $ad->id,
            'reporter_id' => $reporter->id,
            'owner_id' => $ad->user_id,
            'reason' => $payload['reason'],
            'scam_reason' => $payload['scam_reason'] ?? null,
            'payment_methods' => $payload['payment_methods'] ?? null,
            'description' => $payload['description'] ?? null,
            'status' => AdReportStatus::PENDING,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));

        $report->load(['ad', 'reporter', 'owner']);
        $this->dispatchNotifications($reporter, $report);

        return $report;
    }

    private function dispatchNotifications(User $reporter, AdReport $report): void
    {
        $admins = User::query()
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        foreach ($admins as $admin) {
            if (!$this->isDeliverableEmail($admin->email)) {
                Log::warning('Skipping ad report admin email due to undeliverable address.', [
                    'report_id' => $report->id,
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                ]);

                continue;
            }

            try {
                $admin->notify(new NewAdListingReportNotification($report));
            } catch (Throwable $exception) {
                Log::warning('Ad report admin notification failed.', [
                    'report_id' => $report->id,
                    'admin_id' => $admin->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->isDeliverableEmail($reporter->email)) {
            try {
                $reporter->notify(new AdReportReceivedNotification($report));
            } catch (Throwable $exception) {
                Log::warning('Ad report reporter notification failed.', [
                    'report_id' => $report->id,
                    'reporter_id' => $reporter->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function isDeliverableEmail(?string $email): bool
    {
        return is_string($email)
            && filled($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AdminMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendMonthlyReport extends Command
{
    protected $signature = 'app:send-monthly-report';

    protected $description = 'Generate and email the monthly metrics report to all admins';

    public function handle(AdminMetricsService $metricsService): int
    {
        $this->info('Generating monthly report...');

        $metrics = $metricsService->getAllMetricsForExport();

        $pdf = Pdf::loadView('pdf.admin-monthly-report', [
            'metrics' => $metrics,
            'generated_at' => now()->format('d/m/Y à H:i'),
        ])->setPaper('a4');

        $filename = 'reports/keyhome-rapport-'.now()->format('Y-m').'.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        $admins = User::where('role', UserRole::ADMIN)->get();

        foreach ($admins as $admin) {
            Mail::raw(
                'Veuillez trouver ci-joint le rapport mensuel KeyHome pour '.now()->subMonth()->translatedFormat('F Y').'.',
                function ($message) use ($admin, $filename): void {
                    $message->to($admin->email)
                        ->subject('Rapport mensuel KeyHome — '.now()->subMonth()->translatedFormat('F Y'))
                        ->attach(Storage::disk('local')->path($filename));
                }
            );
        }

        $this->info("Report sent to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}

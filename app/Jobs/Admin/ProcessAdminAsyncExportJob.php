<?php

declare(strict_types=1);

namespace App\Jobs\Admin;

use App\Enums\AdminAsyncExportType;
use App\Models\AdminQueuedExport;
use App\Models\Survey;
use App\Models\User;
use App\Services\Admin\AdminAsyncExportGenerator;
use App\Services\Admin\AdminQueuedExportNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ProcessAdminAsyncExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public string $userId,
        public AdminAsyncExportType $type,
        public ?string $surveyId = null,
    ) {}

    public function handle(AdminAsyncExportGenerator $generator): void
    {
        $user = User::query()->findOrFail($this->userId);
        $disk = 'local';
        $exportKey = (string) Str::uuid();
        $dir = 'admin-queued-exports/'.$user->id;

        $survey = match ($this->type) {
            AdminAsyncExportType::SurveyResponsesCsv => Survey::query()->findOrFail($this->surveyId ?? ''),
            default => null,
        };

        $isPdf = $this->type === AdminAsyncExportType::MetricsPdf;
        $relativePath = $dir.'/'.$exportKey.($isPdf ? '.pdf' : '.csv');

        $downloadName = match ($this->type) {
            AdminAsyncExportType::MetricsCsv => 'keyhome-metrics-'.date('Y-m-d').'.csv',
            AdminAsyncExportType::MetricsPdf => 'keyhome-rapport-'.date('Y-m-d').'.pdf',
            AdminAsyncExportType::UsersCsv => 'users-'.now()->format('Y-m-d').'.csv',
            AdminAsyncExportType::AdsCsv => 'ads-'.now()->format('Y-m-d').'.csv',
            AdminAsyncExportType::PaymentsCsv => 'payments-'.now()->format('Y-m-d').'.csv',
            AdminAsyncExportType::SurveyResponsesCsv => sprintf(
                'survey-%s-responses-%s.csv',
                $survey->slug,
                now()->format('Ymd-His'),
            ),
        };

        $mime = $isPdf
            ? 'application/pdf'
            : 'text/csv; charset=UTF-8';

        $absolutePath = Storage::disk($disk)->path($relativePath);

        match ($this->type) {
            AdminAsyncExportType::MetricsCsv => $generator->writeResourceToPath(
                fn ($handle) => $generator->writeMetricsCsv($handle),
                $absolutePath,
            ),
            AdminAsyncExportType::MetricsPdf => Storage::disk($disk)->put(
                $relativePath,
                $generator->generateMetricsPdfBinary(),
            ),
            AdminAsyncExportType::UsersCsv => $generator->writeResourceToPath(
                fn ($handle) => $generator->writeUsersCsv($handle),
                $absolutePath,
            ),
            AdminAsyncExportType::AdsCsv => $generator->writeResourceToPath(
                fn ($handle) => $generator->writeAdsCsv($handle),
                $absolutePath,
            ),
            AdminAsyncExportType::PaymentsCsv => $generator->writeResourceToPath(
                fn ($handle) => $generator->writePaymentsCsv($handle),
                $absolutePath,
            ),
            AdminAsyncExportType::SurveyResponsesCsv => $generator->writeResourceToPath(
                fn ($handle) => $generator->writeSurveyResponsesCsv($survey, $handle),
                $absolutePath,
            ),
        };

        $export = AdminQueuedExport::query()->create([
            'id' => $exportKey,
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $relativePath,
            'download_name' => $downloadName,
            'mime_type' => $mime,
            'expires_at' => now()->addDay(),
        ]);

        AdminQueuedExportNotifier::notifyExportReady($user, $export);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('admin.async_export.failed', [
            'user_id' => $this->userId,
            'type' => $this->type->value,
            'survey_id' => $this->surveyId,
            'exception' => $exception instanceof Throwable ? $exception->getMessage() : null,
        ]);

        $user = User::query()->find($this->userId);
        if ($user instanceof User) {
            AdminQueuedExportNotifier::notifyExportFailed($user);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\AdminAsyncExportType;
use App\Jobs\Admin\ProcessAdminAsyncExportJob;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ExportActionsWidget extends Widget
{
    protected static ?int $sort = 99;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.export-actions';

    public function exportCsv(): void
    {
        /** @var User $user */
        $user = auth()->user();
        ProcessAdminAsyncExportJob::dispatch($user->id, AdminAsyncExportType::MetricsCsv);

        Notification::make()
            ->title('Export en file d\'attente')
            ->body('Vous recevrez une notification Filament dès que le CSV sera prêt (file d\'attente).')
            ->info()
            ->send();
    }

    public function exportPdf(): void
    {
        /** @var User $user */
        $user = auth()->user();
        ProcessAdminAsyncExportJob::dispatch($user->id, AdminAsyncExportType::MetricsPdf);

        Notification::make()
            ->title('Export en file d\'attente')
            ->body('Vous recevrez une notification Filament dès que le PDF sera prêt (file d\'attente).')
            ->info()
            ->send();
    }
}

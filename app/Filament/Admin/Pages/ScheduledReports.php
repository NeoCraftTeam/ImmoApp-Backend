<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\AdminAsyncExportType;
use App\Jobs\Admin\ProcessAdminAsyncExportJob;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ScheduledReports extends Page
{
    protected static string|null|UnitEnum $navigationGroup = 'Rapports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;

    protected static ?string $navigationLabel = 'Rapports';

    protected static ?string $title = 'Rapports et exports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.scheduled-reports';

    /**
     * @return array<string, array{label: string, value: int|string}>
     */
    public function getReportData(): array
    {
        return [
            'users_total' => [
                'label' => 'Utilisateurs inscrits',
                'value' => User::query()->count(),
            ],
            'users_this_month' => [
                'label' => 'Nouveaux ce mois',
                'value' => User::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'ads_total' => [
                'label' => 'Annonces totales',
                'value' => Ad::query()->count(),
            ],
            'ads_active' => [
                'label' => 'Annonces actives',
                'value' => Ad::query()->where('status', 'approved')->where('is_visible', true)->count(),
            ],
            'revenue_this_month' => [
                'label' => 'Revenus ce mois (XOF)',
                'value' => number_format((float) Payment::query()
                    ->where('status', 'success')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('amount')),
            ],
            'reviews_total' => [
                'label' => 'Avis totaux',
                'value' => Review::query()->count(),
            ],
            'avg_rating' => [
                'label' => 'Note moyenne',
                'value' => round((float) (Review::query()->avg('rating') ?? 0), 1).'/5',
            ],
        ];
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportUsers')
                ->label('Exporter les utilisateurs')
                ->icon(Heroicon::ArrowDownTray)
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ProcessAdminAsyncExportJob::dispatch($user->id, AdminAsyncExportType::UsersCsv);
                    Notification::make()
                        ->title('Export en file d\'attente')
                        ->body('Le CSV utilisateurs sera disponible dans vos notifications une fois généré.')
                        ->info()
                        ->send();
                }),
            Action::make('exportAds')
                ->label('Exporter les annonces')
                ->icon(Heroicon::ArrowDownTray)
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ProcessAdminAsyncExportJob::dispatch($user->id, AdminAsyncExportType::AdsCsv);
                    Notification::make()
                        ->title('Export en file d\'attente')
                        ->body('Le CSV annonces sera disponible dans vos notifications une fois généré.')
                        ->info()
                        ->send();
                }),
            Action::make('exportPayments')
                ->label('Exporter les paiements')
                ->icon(Heroicon::ArrowDownTray)
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ProcessAdminAsyncExportJob::dispatch($user->id, AdminAsyncExportType::PaymentsCsv);
                    Notification::make()
                        ->title('Export en file d\'attente')
                        ->body('Le CSV paiements sera disponible dans vos notifications une fois généré.')
                        ->info()
                        ->send();
                }),
        ];
    }
}

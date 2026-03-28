<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
                ->action(fn () => $this->exportUsers()),
            Action::make('exportAds')
                ->label('Exporter les annonces')
                ->icon(Heroicon::ArrowDownTray)
                ->action(fn () => $this->exportAds()),
            Action::make('exportPayments')
                ->label('Exporter les paiements')
                ->icon(Heroicon::ArrowDownTray)
                ->action(fn () => $this->exportPayments()),
        ];
    }

    public function exportUsers(): StreamedResponse
    {
        return Response::streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Prénom', 'Nom', 'Email', 'Rôle', 'Actif', 'Inscrit le'], escape: '\\');

            User::query()->orderByDesc('created_at')->chunk(500, function ($users) use ($handle): void {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        (string) $user->id,
                        (string) $user->firstname,
                        (string) $user->lastname,
                        (string) $user->email,
                        $user->role->value,
                        $user->is_active ? 'Oui' : 'Non',
                        $user->created_at?->toDateString() ?? '',
                    ],
                        escape: '\\');
                }
            });

            fclose($handle);
        }, 'users-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportAds(): StreamedResponse
    {
        return Response::streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Titre', 'Statut', 'Prix', 'Ville', 'Quartier', 'Créé le'], escape: '\\');

            Ad::query()->with(['quarter.city'])->orderByDesc('created_at')->chunk(500, function ($ads) use ($handle): void {
                foreach ($ads as $ad) {
                    fputcsv($handle, [
                        (string) $ad->id,
                        (string) $ad->title,
                        (string) $ad->status->value,
                        (string) $ad->price,
                        (string) ($ad->quarter?->city->name ?? ''),
                        $ad->quarter === null ? '' : (string) $ad->quarter->name,
                        $ad->created_at?->toDateString() ?? '',
                    ],
                        escape: '\\');
                }
            });

            fclose($handle);
        }, 'ads-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportPayments(): StreamedResponse
    {
        return Response::streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Montant', 'Statut', 'Passerelle', 'Utilisateur', 'Créé le'], escape: '\\');

            Payment::query()->with('user')->orderByDesc('created_at')->chunk(500, function ($payments) use ($handle): void {
                foreach ($payments as $payment) {
                    fputcsv($handle, [
                        (string) $payment->id,
                        (string) $payment->amount,
                        (string) $payment->status->value,
                        (string) ($payment->gateway ?? ''),
                        (string) $payment->user->email,
                        $payment->created_at?->toDateString() ?? '',
                    ],
                        escape: '\\');
                }
            });

            fclose($handle);
        }, 'payments-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}

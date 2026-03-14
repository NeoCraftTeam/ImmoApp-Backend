<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Widgets\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportActionsWidget extends Widget
{
    protected static ?int $sort = 99;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.export-actions';

    public function exportCsv(): StreamedResponse
    {
        $metrics = app(AdminMetricsService::class)->getAllMetricsForExport();

        return response()->streamDownload(function () use ($metrics): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['KeyHome — Rapport Métriques Admin', date('d/m/Y H:i')], escape: '\\');
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, ['=== ACQUISITION ==='], escape: '\\');
            fputcsv($handle, ['Visiteurs uniques', $metrics['acquisition']['unique_visitors']], escape: '\\');
            fputcsv($handle, ['Nouvelles inscriptions', $metrics['acquisition']['new_users']], escape: '\\');
            fputcsv($handle, ['Taux de conversion', $metrics['acquisition']['conversion_rate'].'%'], escape: '\\');
            foreach ($metrics['acquisition']['sources'] as $source => $count) {
                fputcsv($handle, ["Source: {$source}", $count], escape: '\\');
            }
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, ['=== ACTIVATION ==='], escape: '\\');
            fputcsv($handle, ['Profils complétés', $metrics['activation']['profile_completion_rate'].'%'], escape: '\\');
            fputcsv($handle, ['Temps 1ère action (h)', $metrics['activation']['avg_time_to_first_action']], escape: '\\');
            fputcsv($handle, ['1ère publication', $metrics['activation']['first_publication_rate'].'%'], escape: '\\');
            fputcsv($handle, ['1ère recherche', $metrics['activation']['first_search_rate'].'%'], escape: '\\');
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, ['=== RÉTENTION ==='], escape: '\\');
            fputcsv($handle, ['DAU', $metrics['retention']['dau']], escape: '\\');
            fputcsv($handle, ['WAU', $metrics['retention']['wau']], escape: '\\');
            fputcsv($handle, ['MAU', $metrics['retention']['mau']], escape: '\\');
            fputcsv($handle, ['Stickiness (DAU/MAU)', $metrics['retention']['stickiness'].'%'], escape: '\\');
            fputcsv($handle, ['Retour 7j', $metrics['retention']['return_rate_7d'].'%'], escape: '\\');
            fputcsv($handle, ['Bailleurs actifs', $metrics['retention']['active_landlords']], escape: '\\');
            fputcsv($handle, ['Bailleurs inactifs', $metrics['retention']['inactive_landlords']], escape: '\\');
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, ['=== REVENU ==='], escape: '\\');
            fputcsv($handle, ['MRR (FCFA)', $metrics['revenue']['mrr']], escape: '\\');
            fputcsv($handle, ['ARPU (FCFA)', $metrics['revenue']['arpu']], escape: '\\');
            fputcsv($handle, ['Churn Rate', $metrics['revenue']['churn_rate'].'%'], escape: '\\');
            foreach ($metrics['revenue']['revenue_by_source'] as $source => $amount) {
                fputcsv($handle, ["Revenu {$source} (FCFA)", $amount], escape: '\\');
            }
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, ['=== TUNNEL DE CONVERSION ==='], escape: '\\');
            foreach ($metrics['funnel']['steps'] as $step) {
                fputcsv($handle, [$step['label'], $step['count'], $step['rate'].'%'], escape: '\\');
            }
            fputcsv($handle, [], escape: '\\');

            fputcsv($handle, ['=== QUALITÉ ==='], escape: '\\');
            fputcsv($handle, ['NPS', $metrics['quality']['nps']], escape: '\\');
            fputcsv($handle, ['Taux signalement', $metrics['quality']['report_rate'].'%'], escape: '\\');
            fputcsv($handle, ['Taux fraude', $metrics['quality']['fraud_rate'].'%'], escape: '\\');
            fputcsv($handle, ['Temps moyen location (j)', $metrics['quality']['avg_time_to_rent']], escape: '\\');
            fputcsv($handle, ['Réponse bailleurs', $metrics['quality']['landlord_response_rate'].'%'], escape: '\\');

            fclose($handle);
        }, 'keyhome-metrics-'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportPdf(): StreamedResponse
    {
        $metrics = app(AdminMetricsService::class)->getAllMetricsForExport();

        $pdf = Pdf::loadView('pdf.admin-monthly-report', [
            'metrics' => $metrics,
            'generated_at' => now()->format('d/m/Y à H:i'),
        ])->setPaper('a4');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'keyhome-rapport-'.date('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}

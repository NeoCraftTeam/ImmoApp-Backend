<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Ad;
use App\Models\AnonymousSurveyResponse;
use App\Models\Payment;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\AdminMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

final readonly class AdminAsyncExportGenerator
{
    public function __construct(private AdminMetricsService $adminMetrics) {}

    /**
     * @param  resource  $handle
     */
    public function writeMetricsCsv($handle): void
    {
        $metrics = $this->adminMetrics->getAllMetricsForExport();

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
    }

    public function generateMetricsPdfBinary(): string
    {
        $metrics = $this->adminMetrics->getAllMetricsForExport();

        $pdf = Pdf::loadView('pdf.admin-monthly-report', [
            'metrics' => $metrics,
            'generated_at' => now()->format('d/m/Y à H:i'),
        ])->setPaper('a4');

        return $pdf->output();
    }

    /**
     * @param  resource  $handle
     */
    public function writeUsersCsv($handle): void
    {
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
    }

    /**
     * @param  resource  $handle
     */
    public function writeAdsCsv($handle): void
    {
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
    }

    /**
     * @param  resource  $handle
     */
    public function writePaymentsCsv($handle): void
    {
        fputcsv($handle, ['ID', 'Montant', 'Statut', 'Passerelle', 'Utilisateur', 'Créé le'], escape: '\\');

        Payment::query()->with('user')->orderByDesc('created_at')->chunk(500, function ($payments) use ($handle): void {
            foreach ($payments as $payment) {
                fputcsv($handle, [
                    (string) $payment->id,
                    (string) $payment->amount,
                    (string) $payment->status->value,
                    (string) ($payment->gateway ?? ''),
                    (string) $payment->user->email,
                    $payment->created_at->toDateString(),
                ],
                    escape: '\\');
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    public function writeSurveyResponsesCsv(Survey $survey, $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'ID réponse',
            'Type',
            'Répondant',
            'Email',
            'Locale',
            'Question',
            'Réponse',
            'Soumis le',
        ],
            escape: '\\');

        SurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->with(['user', 'question'])
            ->orderBy('created_at')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        'client',
                        trim((string) ($row->user->fullname ?: '—')),
                        $row->user->email,
                        (string) ($row->user->locale ?? ''),
                        $row->question->text ?: '—',
                        (string) $row->answer,
                        optional($row->created_at)->format('Y-m-d H:i:s'),
                    ],
                        escape: '\\');
                }
            });

        AnonymousSurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->with('answers.question')
            ->orderBy('created_at')
            ->chunk(500, function ($responses) use ($handle): void {
                foreach ($responses as $response) {
                    foreach ($response->answers as $answer) {
                        fputcsv($handle, [
                            $answer->id,
                            'anonyme',
                            'session#'.mb_substr((string) $response->session_token_hash, 0, 8),
                            '',
                            $response->respondent_audience->value,
                            $answer->question->text ?: '—',
                            (string) $answer->answer,
                            optional($answer->created_at)->format('Y-m-d H:i:s'),
                        ],
                            escape: '\\');
                    }
                }
            });
    }

    public function writeResourceToPath(callable $writer, string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        $handle = fopen($absolutePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open export path for writing.');
        }

        try {
            $writer($handle);
        } finally {
            fclose($handle);
        }
    }
}

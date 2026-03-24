<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

class RegistrationsByAcquisitionChart extends ChartWidget
{
    protected static ?int $sort = 11;

    protected ?string $heading = 'Inscriptions par canal (30 jours)';

    protected int|string|array $columnSpan = 1;

    #[\Override]
    protected function getData(): array
    {
        $rows = Cache::remember(
            'admin_chart_registrations_acquisition_30d',
            300,
            fn (): array => $this->registrationsByAcquisitionRows(),
        );

        $labels = array_column($rows, 'src');
        $data = array_column($rows, 'cnt');

        $palette = [
            'rgba(59, 130, 246, 0.9)',
            'rgba(16, 185, 129, 0.9)',
            'rgba(245, 158, 11, 0.9)',
            'rgba(239, 68, 68, 0.9)',
            'rgba(139, 92, 246, 0.9)',
            'rgba(236, 72, 153, 0.9)',
            'rgba(100, 116, 139, 0.9)',
        ];

        $backgroundColor = [];
        foreach (array_keys($labels) as $i) {
            $backgroundColor[] = $palette[$i % count($palette)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Inscriptions',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return list<array{src: string, cnt: int}>
     */
    private function registrationsByAcquisitionRows(): array
    {
        return DB::table('users')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("COALESCE(acquisition_source, 'unknown') as src")
            ->selectRaw('COUNT(*) as cnt')
            ->groupByRaw("COALESCE(acquisition_source, 'unknown')")
            ->orderByDesc('cnt')
            ->get()
            ->map(fn (stdClass $row): array => [
                'src' => (string) $row->src,
                'cnt' => (int) $row->cnt,
            ])
            ->all();
    }

    #[\Override]
    protected function getType(): string
    {
        return 'doughnut';
    }
}

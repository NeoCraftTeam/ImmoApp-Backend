<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\Widget;

class CohortRetentionChart extends Widget
{
    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.cohort-retention';

    /**
     * @return array<int, array{week: string, cohort_size: int, retention: array<int, float>}>
     */
    public function getCohorts(): array
    {
        return app(AdminMetricsService::class)->getCohortRetention(12);
    }

    /**
     * @return array<int, int>
     */
    public function getRetentionWeeks(): array
    {
        return [1, 2, 4, 8, 12];
    }
}

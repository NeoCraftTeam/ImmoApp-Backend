<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\Widget;

class ConversionFunnelWidget extends Widget
{
    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.conversion-funnel';

    public string $period = '30d';

    /**
     * @return array{steps: array<int, array{label: string, count: int, rate: float, drop_off: float}>}
     */
    public function getFunnelData(): array
    {
        return app(AdminMetricsService::class)->getConversionFunnel($this->period);
    }
}

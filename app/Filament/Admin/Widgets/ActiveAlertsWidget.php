<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\Widget;

class ActiveAlertsWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.active-alerts';

    /**
     * @return array{inactive_landlords: int, low_view_ads: int, fraud_flagged: int, churn_imminent: int, revenue_declining: bool}
     */
    public function getAlerts(): array
    {
        return app(AdminMetricsService::class)->checkAlerts();
    }

    public function hasActiveAlerts(): bool
    {
        $alerts = $this->getAlerts();

        return $alerts['inactive_landlords'] > 0
            || $alerts['low_view_ads'] > 0
            || $alerts['fraud_flagged'] > 0
            || $alerts['churn_imminent'] > 0
            || $alerts['revenue_declining'];
    }
}

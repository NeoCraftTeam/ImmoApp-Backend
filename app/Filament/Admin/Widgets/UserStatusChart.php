<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UserStatusChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Répartition des utilisateurs';

    protected int|string|array $columnSpan = '1/2';

    #[\Override]
    protected function getData(): array
    {
        $activeUsers = User::whereNotNull('email_verified_at')->count();
        $inactiveUsers = User::whereNull('email_verified_at')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Utilisateurs',
                    'data' => [$activeUsers, $inactiveUsers],
                    'backgroundColor' => [
                        'rgb(34, 197, 94)',  // Vert pour actifs
                        'rgb(239, 68, 68)',   // Rouge pour inactifs
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Actifs', 'Inactifs'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; // ou 'pie'
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}

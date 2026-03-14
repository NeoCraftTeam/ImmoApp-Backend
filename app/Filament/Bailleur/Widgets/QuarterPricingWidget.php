<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Widgets;

use App\Models\Ad;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuarterPricingWidget extends Widget
{
    protected string $view = 'filament.bailleur.widgets.quarter-pricing';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getViewData(): array
    {
        $user = Auth::user();

        $ads = Ad::where('user_id', $user->id)
            ->whereNotNull('quarter_id')
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->with('quarter.city')
            ->get();

        $comparisons = [];

        foreach ($ads as $ad) {
            $quarter = $ad->quarter;
            if (!$quarter || !$quarter->avg_price || $quarter->avg_price <= 0) {
                continue;
            }

            $diff = round((((float) $ad->price - (float) $quarter->avg_price) / (float) $quarter->avg_price) * 100, 1);

            $comparisons[] = [
                'ad_title' => $ad->title,
                'ad_price' => (float) $ad->price,
                'quarter_name' => $quarter->name,
                'city_name' => $quarter->city->name ?? '',
                'avg_price' => (float) $quarter->avg_price,
                'diff_percent' => $diff,
                'active_ads' => $quarter->active_ads_count,
            ];
        }

        usort($comparisons, fn (array $a, array $b): int => abs((int) $b['diff_percent']) <=> abs((int) $a['diff_percent']));

        return [
            'comparisons' => array_slice($comparisons, 0, 5),
            'hasData' => count($comparisons) > 0,
        ];
    }
}

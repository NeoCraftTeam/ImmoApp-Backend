<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Quarter;
use Illuminate\Console\Command;

class RecalculateQuarterPricing extends Command
{
    protected $signature = 'quarters:recalculate-pricing';

    protected $description = 'Recalculate average pricing data for all quarters based on active ads';

    public function handle(): int
    {
        $quarters = Quarter::all();
        $bar = $this->output->createProgressBar($quarters->count());

        $updated = 0;

        foreach ($quarters as $quarter) {
            $activeAds = Ad::where('quarter_id', $quarter->id)
                ->whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->where('is_visible', true)
                ->whereNotNull('price')
                ->where('price', '>', 0);

            $count = $activeAds->count();
            $avgPrice = $count > 0 ? (float) $activeAds->avg('price') : null;

            $avgPricePerSqm = null;
            if ($count > 0) {
                $withSurface = (clone $activeAds)->where('surface_area', '>', 0);
                if ($withSurface->count() > 0) {
                    $avgPricePerSqm = (float) $withSurface->selectRaw('AVG(price / surface_area) as avg_sqm')->value('avg_sqm');
                }
            }

            $quarter->update([
                'avg_price' => $avgPrice ? round($avgPrice, 2) : null,
                'avg_price_per_sqm' => $avgPricePerSqm ? round($avgPricePerSqm, 2) : null,
                'active_ads_count' => $count,
                'pricing_updated_at' => now(),
            ]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$updated} quartiers mis à jour.");

        return self::SUCCESS;
    }
}

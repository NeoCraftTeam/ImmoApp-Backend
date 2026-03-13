<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ad;
use App\Services\TourService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackfillTourPanoMetadata extends Command
{
    protected $signature = 'tour:backfill-pano-metadata
                            {--ad= : Process a specific ad ID only}
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Backfill haov/vaov/vOffset metadata on existing tour scenes by downloading and analyzing each image.';

    public function handle(TourService $tourService): int
    {
        $query = Ad::query()->where('has_3d_tour', true)->whereNotNull('tour_config');

        if ($adId = $this->option('ad')) {
            $query->where('id', $adId);
        }

        $ads = $query->get();

        if ($ads->isEmpty()) {
            $this->info('No ads with 3D tours found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$ads->count()} ad(s)...");
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($ads as $ad) {
            $config = $ad->tour_config ?? [];
            $scenes = $config['scenes'] ?? [];
            $updated = false;

            foreach ($scenes as &$scene) {
                if (isset($scene['haov']) && $scene['haov'] > 0) {
                    $this->line("  ⏭ Scene {$scene['id']}: already has haov={$scene['haov']}, skipping.");
                    $totalSkipped++;

                    continue;
                }

                $imagePath = $scene['image_path'] ?? null;
                if (!$imagePath || !is_string($imagePath)) {
                    $this->warn("  ⚠ Scene {$scene['id']}: no image_path stored, skipping.");
                    $totalSkipped++;

                    continue;
                }

                if (!Storage::disk()->exists($imagePath)) {
                    $this->warn("  ⚠ Scene {$scene['id']}: file not found at {$imagePath}, skipping.");
                    $totalSkipped++;

                    continue;
                }

                $tmpFile = tempnam(sys_get_temp_dir(), 'pano_backfill_');
                try {
                    $stream = Storage::disk()->readStream($imagePath);
                    if (!$stream) {
                        $this->warn("  ⚠ Scene {$scene['id']}: unable to read stream for {$imagePath}, skipping.");
                        $totalSkipped++;

                        continue;
                    }

                    $header = stream_get_contents($stream, 65536);
                    $hasGPano = $header !== false && str_contains($header, 'GPano');

                    if ($hasGPano) {
                        rewind($stream);
                        file_put_contents($tmpFile, stream_get_contents($stream));
                    } else {
                        file_put_contents($tmpFile, $header);
                    }
                    fclose($stream);

                    $meta = $tourService->extractPanoMetadata($tmpFile);

                    $scene['haov'] = $meta['haov'];
                    $scene['vaov'] = $meta['vaov'];
                    $scene['vOffset'] = $meta['vOffset'];
                    $scene['is_partial_pano'] = $meta['is_partial'];
                    $updated = true;
                    $totalUpdated++;

                    $partial = $meta['is_partial'] ? ' (PARTIAL)' : '';
                    $this->info("  ✅ Scene {$scene['id']}: haov={$meta['haov']} vaov={$meta['vaov']} vOffset={$meta['vOffset']}{$partial}");
                } finally {
                    @unlink($tmpFile);
                }
            }
            unset($scene);

            if ($updated && !$this->option('dry-run')) {
                $config['scenes'] = $scenes;

                DB::transaction(function () use ($ad, $config): void {
                    /** @var Ad|null $lockedAd */
                    $lockedAd = Ad::query()->whereKey($ad->getKey())->lockForUpdate()->first();
                    $lockedAd?->update(['tour_config' => $config]);
                }, attempts: 3);

                $this->info("  💾 Ad {$ad->id} updated in database.");
            } elseif ($this->option('dry-run') && $updated) {
                $this->comment("  [DRY-RUN] Would update ad {$ad->id}.");
            }
        }

        $this->newLine();
        $this->info("Done. Updated: {$totalUpdated} scene(s), Skipped: {$totalSkipped} scene(s).");

        return self::SUCCESS;
    }
}

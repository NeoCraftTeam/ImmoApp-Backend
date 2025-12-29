<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateAdImagesToSpatie extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-ad-images-to-spatie';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of AdImages to Spatie Media Library...');

        // Query directly the table since Models might be missing relations
        $oldImages = \Illuminate\Support\Facades\DB::table('ad_images')
            ->orderBy('ad_id')
            ->orderBy('is_primary', 'desc')
            ->get();

        $count = $oldImages->count();
        if ($count === 0) {
            $this->info('No images found in ad_images table.');

            return;
        }

        $this->info("Found {$count} images to migrate.");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($oldImages as $oldImage) {
            $ad = \App\Models\Ad::find($oldImage->ad_id);

            // Skip if ad deleted
            if (!$ad) {
                $bar->advance();

                continue;
            }

            $relativePath = $oldImage->image_path;

            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
                try {
                    // Check if already migrated
                    $alreadyMigrated = $ad->getMedia('images')->contains(fn ($media) => $media->getCustomProperty('old_id') === $oldImage->id);

                    if (!$alreadyMigrated) {
                        $ad->addMediaFromDisk($relativePath, 'public')
                            ->preservingOriginal()
                            ->withCustomProperties(['old_id' => $oldImage->id])
                            ->toMediaCollection('images');
                    }
                } catch (\Exception $e) {
                    // Log error but continue
                    $this->error("\nFailed to migrate image {$oldImage->id}: ".$e->getMessage());
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Migration completed successfully.');
    }
}

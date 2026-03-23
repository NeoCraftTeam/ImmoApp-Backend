<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Support\GeoLocation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * Creates a new ad with media attachments.
 *
 * Extracted from AdController::store() to make ad creation
 * reusable from API, Filament, bulk import, etc.
 */
final class CreateAd
{
    public function __construct(private LoggerInterface $log) {}

    /**
     * @param  array<string, mixed>  $data  Validated ad attributes
     * @param  array<int, UploadedFile>  $images  Image files to attach
     */
    public function execute(array $data, array $images = []): Ad
    {
        return DB::transaction(function () use ($data, $images): Ad {
            $ad = new Ad;
            $ad->fill([
                'title' => $data['title'],
                'description' => $data['description'],
                'adresse' => $data['adresse'],
                'price' => $data['price'],
                'surface_area' => $data['surface_area'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'has_parking' => $data['has_parking'] ?? false,
                'location' => GeoLocation::fromArray($data)?->toPoint(),
                'expires_at' => $data['expires_at'] ?? null,
                'user_id' => auth()->id(),
                'quarter_id' => $data['quarter_id'],
                'type_id' => $data['type_id'],
                'attributes' => $data['attributes'] ?? [],
            ]);
            $ad->forceFill(['status' => AdStatus::PENDING]);
            $ad->save();

            $this->log->info('Ad created with ID: '.$ad->id);

            $this->attachImages($ad, $images);

            return $ad;
        });
    }

    /**
     * @param  array<int, UploadedFile>  $images
     */
    private function attachImages(Ad $ad, array $images): void
    {
        foreach ($images as $image) {
            $ad->addMedia($image)->toMediaCollection('images');
        }
    }
}

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
final readonly class CreateAd
{
    public function __construct(private LoggerInterface $log) {}

    /**
     * @param  array<string, mixed>  $data  Validated ad attributes
     * @param  array<int, UploadedFile>  $images  Image files to attach
     * @param  UploadedFile|null  $propertyConditionPdf  Optional PDF document
     */
    public function execute(array $data, array $images = [], ?UploadedFile $propertyConditionPdf = null): Ad
    {
        return DB::transaction(function () use ($data, $images, $propertyConditionPdf): Ad {
            $ad = new Ad;
            $ad->fill([
                // Core fields
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

                // Premium lease conditions
                'deposit_amount' => $data['deposit_amount'] ?? null,
                'minimum_lease_duration' => $data['minimum_lease_duration'] ?? null,

                // Charges
                'charges_forfaitaires' => $data['charges_forfaitaires'] ?? false,
                'charges_montant_forfait' => $data['charges_montant_forfait'] ?? null,
                'charges_eau' => $data['charges_eau'] ?? null,
                'charges_electricite' => $data['charges_electricite'] ?? null,
                'charges_autres' => $data['charges_autres'] ?? null,

                // Proximity distances (metres)
                'distance_main_road_m' => $data['distance_main_road_m'] ?? null,
                'distance_shops_m' => $data['distance_shops_m'] ?? null,
                'distance_transport_m' => $data['distance_transport_m'] ?? null,
                'distance_school_m' => $data['distance_school_m'] ?? null,
                'distance_hospital_m' => $data['distance_hospital_m'] ?? null,

                // Note: is_boost_requested is a frontend-only flag; actual boost
                // is applied post-approval via is_boosted / boost_score columns.
            ]);
            $ad->forceFill(['status' => AdStatus::PENDING]);
            $ad->save();

            $this->log->info('Ad created with ID: '.$ad->id);

            $this->attachImages($ad, $images);

            if ($propertyConditionPdf instanceof UploadedFile) {
                $ad->addMedia($propertyConditionPdf)->toMediaCollection('property_condition');
            }

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

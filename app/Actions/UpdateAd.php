<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AdStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Ad;
use App\Support\GeoLocation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * Updates an existing ad with optional media changes and status transitions.
 *
 * Extracted from AdController::update() to make ad updates
 * reusable from API, Filament, and scheduled jobs.
 */
final readonly class UpdateAd
{
    public function __construct(private LoggerInterface $log) {}

    /**
     * @param  array<string, mixed>  $data  Validated ad attributes
     * @param  array<int, UploadedFile>  $newImages  New images to add
     * @param  array<int, string>  $imagesToDelete  Media IDs to remove
     * @return array{ad: Ad, status_changed: bool}
     */
    public function execute(Ad $ad, array $data, array $newImages = [], array $imagesToDelete = []): array
    {
        return DB::transaction(function () use ($ad, $data, $newImages, $imagesToDelete): array {
            $geo = GeoLocation::fromArray($data);
            if ($geo) {
                $data['location'] = $geo->toPoint();
            }

            $statusChanged = false;
            $newStatus = null;

            if (isset($data['status'])) {
                $newStatus = AdStatus::from($data['status']);
                if ($ad->status !== $newStatus && !$ad->status->canTransitionTo($newStatus)) {
                    throw new InvalidStatusTransitionException($ad->status, $newStatus);
                }
                unset($data['status']);
            }

            unset($data['user_id'], $data['agency_id']);

            $ad->update($data);

            if ($newStatus !== null && $ad->status !== $newStatus) {
                $ad->forceFill(['status' => $newStatus])->save();
                $statusChanged = true;
            }

            $this->log->info('Ad updated with ID: '.$ad->id);

            foreach ($newImages as $image) {
                $ad->addMedia($image)->toMediaCollection('images');
            }

            foreach ($imagesToDelete as $mediaId) {
                $media = $ad->media()->find($mediaId);
                $media?->delete();
            }

            return ['ad' => $ad, 'status_changed' => $statusChanged];
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Organises Spatie Media Library files on the storage disk by entity type,
 * keeping all files related to a given record under one predictable prefix:
 *
 *   ads/{adId}/images/{mediaId}/file.webp          ← Ad "images" collection
 *   ads/{adId}/documents/{mediaId}/file.pdf         ← Ad "property_condition" collection
 *   avatars/{userId}/{mediaId}/avatar.webp           ← User "avatars" collection
 *   {model_type}s/{modelId}/{collection}/{mediaId}/ ← anything else (safe default)
 */
class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'/responsive-images/';
    }

    private function basePath(Media $media): string
    {
        $type = class_basename((string) $media->model_type);
        $modelId = $media->model_id;
        $collection = $media->collection_name;
        $mediaId = $media->getKey();

        return match (true) {
            $type === 'Ad' && $collection === 'images' => "ads/{$modelId}/images/{$mediaId}",
            $type === 'Ad' && $collection === 'property_condition' => "ads/{$modelId}/documents/{$mediaId}",
            $type === 'User' && $collection === 'avatars' => "avatars/{$modelId}/{$mediaId}",
            default => strtolower($type).'s/'.$modelId.'/'.$collection.'/'.$mediaId,
        };
    }
}

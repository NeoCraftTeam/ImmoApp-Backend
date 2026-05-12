<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Normalizes stored avatar paths and third-party URLs for API/chat payloads.
 */
final class ChatAvatarUrl
{
    public static function resolve(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $avatar = trim($raw);
        if ($avatar === '' || $avatar === '0') {
            return null;
        }

        if (str_starts_with($avatar, '//')) {
            return 'https:'.$avatar;
        }

        if (preg_match('#^https?://#i', $avatar) === 1) {
            return $avatar;
        }

        $disk = config('filesystems.app_media_disk');

        return Storage::disk($disk)->url($avatar);
    }
}

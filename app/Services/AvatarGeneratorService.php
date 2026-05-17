<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravolt\Avatar\Facade as AvatarFacade;

/**
 * Generates and assigns a default avatar for a user using Laravolt Avatar.
 *
 * Responsible for: creating the avatar image, storing it to disk,
 * and updating the user's avatar field. Extracted from User model
 * to keep the model free of file I/O side effects.
 */
final class AvatarGeneratorService
{
    /**
     * Generate an avatar from the user's name, persist it to the configured
     * media disk, and update the user's `avatar` attribute in memory.
     *
     * The caller is responsible for persisting the model after this call
     * (or not — the `creating` boot hook does not save separately).
     */
    public function generateAndAssign(User $user): void
    {
        $name = trim(($user->firstname ?? '').' '.($user->lastname ?? ''));
        if ($name === '') {
            $name = 'U';
        }

        $storagePath = 'avatars/'.$user->id.'/avatar.webp';
        $tempFile = sys_get_temp_dir().'/avatar_'.uniqid('', true).'.png';

        AvatarFacade::create($name)->save($tempFile, 80);

        Storage::disk(config('filesystems.app_media_disk'))->put(
            $storagePath,
            (string) file_get_contents($tempFile),
        );

        @unlink($tempFile);

        $user->avatar = $storagePath;
    }
}

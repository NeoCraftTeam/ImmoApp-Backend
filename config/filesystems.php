<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Tour Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for 3D tour assets (R2 in production). When S3-compatible,
    | the proxy redirects to signed URLs instead of streaming through PHP.
    |
    */
    'tour_disk' => env('TOUR_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | App Media Disk (logos, avatars, contracts)
    |--------------------------------------------------------------------------
    |
    | Disk for app media: agency logos, user avatars, lease contract PDFs.
    | When FILESYSTEM_DISK=r2, these are stored on R2 with this structure:
    |   avatars/{userId}/avatar.webp
    |   agency-logos/{agencyId}/{filename}
    |   lease-contracts/{adSlug}-{date}.pdf
    |
    */
    'app_media_disk' => env('APP_MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // ─── LIVEWIRE TEMPORARY UPLOADS ──────────────────────────────────
        'tmp' => [
            'driver' => 'local',
            'root' => storage_path('app/tmp'),
            'throw' => false,
        ],

        // ─── BACKUPS (Cloudflare R2) ─────────────────────────────────────
        // Production: set BACKUP_DISKS=backups in .env
        // Required vars: BACKUP_R2_ACCESS_KEY_ID, BACKUP_R2_SECRET_ACCESS_KEY,
        //                BACKUP_R2_BUCKET, BACKUP_R2_ENDPOINT (https://{id}.r2.cloudflarestorage.com)
        'backups' => [
            'driver' => 's3',
            'key' => env('BACKUP_R2_ACCESS_KEY_ID'),
            'secret' => env('BACKUP_R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('BACKUP_R2_BUCKET', 'keyhome-db-backups'),
            'endpoint' => env('BACKUP_R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            // ⚠️ R2 ne supporte pas les ACLs — obligatoire
            'retain_visibility' => false,
            'throw' => false,
        ],

        // ─── CLOUDFLARE R2 ────────────────────────────────────────────
        'r2' => [
            'driver' => 's3',      // R2 = compatible S3
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
            'throw' => false,
            // ⚠️ CRITIQUE — R2 ne supporte pas les ACLs
            // Sans cette ligne tu auras une erreur "ACL not supported"
            'retain_visibility' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Encryption Key
    |--------------------------------------------------------------------------
    | 32-byte hex string used for AES-256-CBC message body encryption.
    | Generate with: php -r "echo bin2hex(random_bytes(32));"
    */
    'encryption_key' => env('CHAT_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (requests per minute)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'send_message' => 60,
        'upload_attachment' => 10,
        'set_typing' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Constraints
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'image_max_mb' => 10,
        'file_max_mb' => 20,
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'conversations' => 20,
        'messages' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase / FCM
    |--------------------------------------------------------------------------
    */
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase-credentials.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | R2 Attachment Storage Path Prefix
    |--------------------------------------------------------------------------
    */
    'attachment_prefix' => 'chats',

    /*
    |--------------------------------------------------------------------------
    | Signed URL TTL (hours)
    |--------------------------------------------------------------------------
    */
    'signed_url_ttl_hours' => 24,
];

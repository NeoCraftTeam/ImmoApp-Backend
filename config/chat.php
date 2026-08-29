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
    | Client-sealed (E2EE) messages
    |--------------------------------------------------------------------------
    | When false (default since May 2026), the server transparently ignores any
    | `is_client_sealed=true` payload and falls back to server-side AES
    | encryption (CHAT_ENCRYPTION_KEY). This trades end-to-end confidentiality
    | for portability: a user opening their account on a new device or browser
    | can immediately read their full history. WhatsApp Web / Telegram model.
    |
    | Flip to true to re-enable optional client-sealed messages (requires the
    | frontend E2EE bootstrap to run again and the historic device key to be
    | available). Legacy messages stored with is_client_sealed=true remain in
    | the database and are still served as such; clients without the matching
    | private key render a graceful fallback.
    */
    'client_sealed_enabled' => env('CHAT_CLIENT_SEALED_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (requests per minute)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'send_message' => 60,
        'upload_attachment' => 10,
        'set_typing' => 30,
        'reaction' => 60,
        'e2ee_identity_update' => 20,
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
        // Max messages returned by the WhatsApp Web-style delta sync
        // (GET /messages?after=<UTC>). Caps a long-absence catch-up so the
        // client paginates the delta instead of pulling an unbounded batch.
        'messages_delta_max' => 200,
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

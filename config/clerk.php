<?php

declare(strict_types=1);

return [
    'publishable_key' => env('CLERK_PUBLISHABLE_KEY', env('NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY', '')),
    'secret_key' => env('CLERK_SECRET_KEY', ''),
    'jwks_url' => env('CLERK_JWKS_URL', ''),
    'webhook_secret' => env('CLERK_WEBHOOK_SECRET', ''),
];

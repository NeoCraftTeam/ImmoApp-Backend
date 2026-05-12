<?php

declare(strict_types=1);

/**
 * Assert config/reverb broadcaster options without relying on a full app reboot.
 * `config/broadcasting.php` calls env() at load time.
 */
it('uses REVERB_BROADCAST_* for Guzzle reverb target when set', function (): void {
    $keys = [
        'REVERB_APP_KEY',
        'REVERB_APP_SECRET',
        'REVERB_APP_ID',
        'REVERB_BROADCAST_HOST',
        'REVERB_BROADCAST_PORT',
        'REVERB_BROADCAST_SCHEME',
        'REVERB_HOST',
        'REVERB_PORT',
        'REVERB_SCHEME',
    ];

    $saved = [];
    foreach ($keys as $key) {
        $saved[$key] = getenv($key) === false ? null : getenv($key);
    }

    putenv('REVERB_APP_KEY=dummykeydummykey12');
    putenv('REVERB_APP_SECRET=dummysecretsecretsecretsecretsecretsecret01');
    putenv('REVERB_APP_ID=keyhome_test');
    putenv('REVERB_BROADCAST_HOST=reverb');
    putenv('REVERB_BROADCAST_PORT=8080');
    putenv('REVERB_BROADCAST_SCHEME=http');
    putenv('REVERB_HOST=public.reverb.example');
    putenv('REVERB_PORT=443');
    putenv('REVERB_SCHEME=https');

    /** @var array<string, mixed> $cfg */
    $cfg = require dirname(__DIR__, 2).'/config/broadcasting.php';

    foreach ($saved as $key => $value) {
        if ($value === null) {
            putenv($key);
        } else {
            putenv($key.'='.$value);
        }
    }

    expect($cfg['connections']['reverb']['options']['host'])->toBe('reverb')
        ->and($cfg['connections']['reverb']['options']['port'])->toBe('8080')
        ->and($cfg['connections']['reverb']['options']['scheme'])->toBe('http')
        ->and($cfg['connections']['reverb']['options']['useTLS'])->toBeFalse();
});

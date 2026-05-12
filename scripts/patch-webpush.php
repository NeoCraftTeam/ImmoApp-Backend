#!/usr/bin/env php
<?php

/**
 * Patch minishlink/web-push Encryption.php for OpenSSL 3.6+ compatibility.
 *
 * OpenSSL 3.6 rejects P-256 EC keys in openssl_pkey_new() because it enforces
 * a minimum of 384 bits. This patch adds a fallback using the openssl CLI.
 *
 * This script is run automatically via composer post-install/update hooks.
 */
$file = __DIR__.'/../vendor/minishlink/web-push/src/Encryption.php';

if (!file_exists($file)) {
    echo "⏭ minishlink/web-push not installed, skipping patch.\n";
    exit(0);
}

$content = file_get_contents($file);

// Already patched?
if (str_contains($content, 'Fallback for OpenSSL 3.6+')) {
    echo "✓ web-push Encryption.php already patched.\n";
    exit(0);
}

$original = <<<'PHP'
        $keyResource = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$keyResource) {
            throw new \RuntimeException('Unable to create the local key.');
        }
PHP;

$patched = <<<'PHP'
        // Try standard openssl_pkey_new first
        $keyResource = @openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        // Fallback for OpenSSL 3.6+ which rejects P-256 (< 384 bits) via openssl_pkey_new()
        if (!$keyResource) {
            $pem = shell_exec('openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:prime256v1 2>/dev/null');
            if ($pem) {
                $keyResource = openssl_pkey_get_private($pem);
            }
        }

        if (!$keyResource) {
            throw new \RuntimeException(
                'Unable to create the local EC key. '
                . 'OpenSSL 3.6+ rejects P-256 in openssl_pkey_new(). '
                . 'Ensure the "openssl" CLI is available or downgrade OpenSSL.'
            );
        }
PHP;

if (!str_contains($content, 'Unable to create the local key.')) {
    echo "⚠ web-push Encryption.php has unexpected content, skipping patch.\n";
    exit(0);
}

$content = str_replace($original, $patched, $content);
file_put_contents($file, $content);

echo "✓ Patched web-push Encryption.php for OpenSSL 3.6+ compatibility.\n";

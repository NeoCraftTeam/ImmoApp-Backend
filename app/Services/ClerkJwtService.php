<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies Clerk session JWTs using the JWKS endpoint (no external library),
 * then fetches the user's profile from the Clerk Backend API.
 */
class ClerkJwtService
{
    /**
     * Verify the JWT and return the Clerk user profile, or null if invalid.
     *
     * @return array<string,mixed>|null
     */
    public function verifyAndFetchUser(string $sessionToken): ?array
    {
        // Step 1 — verify signature & expiry using JWKS
        $payload = $this->verifyJwt($sessionToken);

        if ($payload === null) {
            return null;
        }

        $clerkUserId = $payload['sub'] ?? null;

        if (!is_string($clerkUserId) || $clerkUserId === '') {
            return null;
        }

        // Step 2 — fetch full profile from Clerk Backend API
        $secretKey = config('clerk.secret_key', '');

        if ($secretKey === '') {
            Log::error('CLERK_SECRET_KEY is not configured.');

            return null;
        }

        try {
            $response = Http::withToken($secretKey)
                ->timeout(5)
                ->get("https://api.clerk.com/v1/users/{$clerkUserId}");

            if (!$response->ok()) {
                Log::warning('Clerk user fetch failed', [
                    'status' => $response->status(),
                    'clerk_user_id' => $clerkUserId,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Clerk user fetch exception: '.$e->getMessage());

            return null;
        }
    }

    // -------------------------------------------------------------------------
    // JWT verification via JWKS (openssl, no external packages)
    // -------------------------------------------------------------------------

    /**
     * Decode and verify a JWT, returning the payload array or null.
     *
     * @return array<string,mixed>|null
     */
    private function verifyJwt(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->b64UrlDecode($headerB64), true);
        $payload = json_decode($this->b64UrlDecode($payloadB64), true);

        if (!is_array($header) || !is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        $pem = $this->getPublicKeyPem($header['kid'] ?? null, $payload['iss'] ?? null);

        if ($pem === null) {
            return null;
        }

        $signature = $this->b64UrlDecode($signatureB64);
        $verified = openssl_verify($headerB64.'.'.$payloadB64, $signature, $pem, OPENSSL_ALGO_SHA256);

        return $verified === 1 ? $payload : null;
    }

    /**
     * Fetch and cache the RSA PEM public key for the given key ID.
     */
    private function getPublicKeyPem(?string $kid, ?string $issuer): ?string
    {
        $cacheKey = 'clerk_jwk_'.($kid ?? 'default');

        return Cache::remember($cacheKey, now()->addHour(), function () use ($kid, $issuer): ?string {
            $jwksUrl = $this->resolveJwksUrl($issuer);

            if ($jwksUrl === '') {
                return null;
            }

            try {
                $response = Http::timeout(5)->get($jwksUrl);

                if (!$response->ok()) {
                    return null;
                }

                foreach ($response->json('keys', []) as $jwk) {
                    if ($kid === null || ($jwk['kid'] ?? null) === $kid) {
                        return $this->jwkToPem($jwk);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Clerk JWKS fetch failed: '.$e->getMessage());
            }

            return null;
        });
    }

    /**
     * Resolve the JWKS URL using the following priority order:
     *  1. CLERK_JWKS_URL env variable (explicit, always works)
     *  2. JWT `iss` claim (available once a token is being verified)
     *  3. Derived from CLERK_PUBLISHABLE_KEY (base64url-encoded domain)
     *  4. Error — one of the above must be set.
     */
    private function resolveJwksUrl(?string $issuer): string
    {
        // 1. Explicit override — set this in .env when you change Clerk instances
        $explicit = (string) config('clerk.jwks_url', '');

        if ($explicit !== '') {
            return $explicit;
        }

        // 2. Try from JWT `iss` claim (most reliable at runtime)
        if ($issuer !== null && str_contains($issuer, 'clerk')) {
            return rtrim($issuer, '/').'/.well-known/jwks.json';
        }

        // 3. Derive from CLERK_PUBLISHABLE_KEY: pk_test_BASE64URL where
        // BASE64URL decodes to "domain$" (e.g. "flexible-parrot-7.clerk.accounts.dev$")
        $key = (string) config('clerk.publishable_key', '');
        $parts = explode('_', $key);

        if (count($parts) >= 3) {
            $decoded = $this->b64UrlDecode($parts[2]);
            $domain = rtrim($decoded, "\x00$");

            if ($domain !== '') {
                return "https://{$domain}/.well-known/jwks.json";
            }
        }

        // Last resort — should not happen if CLERK_JWKS_URL or CLERK_PUBLISHABLE_KEY is set
        Log::error('ClerkJwtService: cannot resolve JWKS URL. Set CLERK_JWKS_URL in your .env.');

        return '';
    }

    /**
     * Convert an RSA JWK to a PEM-encoded public key string.
     *
     * @param  array<string,mixed>  $jwk
     */
    private function jwkToPem(array $jwk): ?string
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            return null;
        }

        $n = $this->b64UrlDecode((string) $jwk['n']);
        $e = $this->b64UrlDecode((string) $jwk['e']);

        // DER-encode integers, then wrap in RSAPublicKey SEQUENCE
        $nDer = $this->derInt($n);
        $eDer = $this->derInt($e);
        $rsaSeq = $this->derSeq($nDer.$eDer);

        // Wrap in SubjectPublicKeyInfo
        $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitStr = "\x03".$this->derLen(1 + strlen($rsaSeq))."\x00".$rsaSeq;
        $spki = $this->derSeq($algId.$bitStr);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function derInt(string $bytes): string
    {
        if (ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLen(strlen($bytes)).$bytes;
    }

    private function derSeq(string $contents): string
    {
        return "\x30".$this->derLen(strlen($contents)).$contents;
    }

    private function derLen(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }

        $packed = ltrim(pack('N', $len), "\x00");

        return chr(0x80 | strlen($packed)).$packed;
    }

    private function b64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;

        if ($pad !== 0) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}

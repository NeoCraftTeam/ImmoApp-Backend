<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Support\CountryCurrency;
use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Résout le pays d'un visiteur depuis son adresse IP via la base locale
 * MaxMind GeoLite2, puis en déduit la devise d'affichage.
 *
 * Chaîne de résolution (première source valable gagne) :
 *   1. Base MaxMind GeoLite2 sur l'IP client réelle (X-Forwarded-For géré
 *      par les trusted proxies de Laravel) — hors-ligne, privé, rapide.
 *   2. En-tête edge `CF-IPCountry` (si le site passe par Cloudflare).
 *   3. `null` → la devise retombe sur la devise de base (XAF).
 *
 * La base .mmdb n'est pas versionnée : si le fichier est absent ou
 * illisible, on saute proprement à l'étape 2 sans jamais lever d'erreur.
 */
final class GeoLocationService
{
    private ?Reader $reader = null;

    private bool $readerResolved = false;

    /**
     * Code pays ISO 3166-1 alpha-2 (majuscules) du visiteur, ou null.
     */
    public function countryForRequest(Request $request): ?string
    {
        $ip = $request->ip();
        if (is_string($ip) && $ip !== '') {
            $fromDb = $this->countryForIp($ip);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        // Repli : en-tête pays fourni par un edge (Cloudflare / Vercel).
        $header = $request->header('CF-IPCountry')
            ?? $request->header('X-Vercel-IP-Country')
            ?? $request->header('X-Country');
        if (is_string($header)) {
            $code = strtoupper(trim($header));
            if (!in_array($code, ['', 'XX', 'T1'], true)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Devise d'affichage résolue pour la requête (jamais null — repli XAF).
     *
     * @return array{country: string|null, currency: string, source: string}
     */
    public function currencyForRequest(Request $request): array
    {
        $country = $this->countryForRequest($request);

        return [
            'country' => $country,
            'currency' => CountryCurrency::forCountry($country),
            'source' => $country === null ? 'fallback' : 'ip',
        ];
    }

    /**
     * Interroge la base MaxMind pour une IP. Retourne null si la base est
     * absente/illisible, l'IP privée/introuvable, ou toute autre erreur.
     */
    public function countryForIp(string $ip): ?string
    {
        $reader = $this->reader();
        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->country($ip);
            $iso = $record->country->isoCode;

            return is_string($iso) && $iso !== '' ? strtoupper($iso) : null;
        } catch (Throwable) {
            // IP privée/réservée, absente de la base, ou format invalide.
            return null;
        }
    }

    /**
     * Charge (paresseusement, une seule fois) le lecteur MaxMind. Renvoie
     * null si la base n'est pas déployée — le repli en-tête prend le relais.
     */
    private function reader(): ?Reader
    {
        if ($this->readerResolved) {
            return $this->reader;
        }
        $this->readerResolved = true;

        $path = (string) config('services.maxmind.db_path');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        try {
            $this->reader = new Reader($path);
        } catch (Throwable $e) {
            Log::warning('GeoLite2 database unreadable — falling back to header/null geo', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            $this->reader = null;
        }

        return $this->reader;
    }
}

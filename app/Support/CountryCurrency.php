<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Table de correspondance pays (ISO 3166-1 alpha-2) → devise d'affichage
 * (ISO 4217). Alignée sur la table du frontend web (`src/lib/currency.ts`)
 * et sur les devises que l'app sait convertir. La devise de base (BDD) est
 * XAF ; tout pays inconnu retombe dessus.
 */
final class CountryCurrency
{
    public const string BASE_CURRENCY = 'XAF';

    /** @var array<string, string> */
    private const array MAP = [
        // Afrique centrale CFA (CEMAC)
        'CM' => 'XAF', 'GA' => 'XAF', 'CG' => 'XAF', 'TD' => 'XAF', 'CF' => 'XAF', 'GQ' => 'XAF',
        // Afrique de l'Ouest CFA (UEMOA)
        'SN' => 'XOF', 'CI' => 'XOF', 'BJ' => 'XOF', 'TG' => 'XOF', 'BF' => 'XOF',
        'ML' => 'XOF', 'NE' => 'XOF', 'GW' => 'XOF',
        // Afrique non-CFA
        'NG' => 'NGN', 'GH' => 'GHS', 'KE' => 'KES', 'ZA' => 'ZAR', 'MA' => 'MAD', 'EG' => 'EGP',
        // Zone euro
        'FR' => 'EUR', 'BE' => 'EUR', 'DE' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'PT' => 'EUR',
        'NL' => 'EUR', 'LU' => 'EUR', 'IE' => 'EUR', 'AT' => 'EUR', 'FI' => 'EUR', 'GR' => 'EUR',
        // Europe hors euro
        'GB' => 'GBP', 'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN',
        // Amériques
        'US' => 'USD', 'CA' => 'CAD', 'BR' => 'BRL', 'MX' => 'MXN',
        // Moyen-Orient
        'AE' => 'AED', 'SA' => 'SAR', 'TR' => 'TRY',
        // Asie / Océanie
        'CN' => 'CNY', 'JP' => 'JPY', 'KR' => 'KRW', 'IN' => 'INR', 'AU' => 'AUD',
    ];

    /**
     * Devise d'affichage pour un code pays. Insensible à la casse ;
     * repli sur la devise de base pour un pays inconnu ou vide.
     */
    public static function forCountry(?string $countryCode): string
    {
        if ($countryCode === null || trim($countryCode) === '') {
            return self::BASE_CURRENCY;
        }

        return self::MAP[strtoupper(trim($countryCode))] ?? self::BASE_CURRENCY;
    }
}

<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for natural-language ad search parsing.
 *
 * Implementations transform a free-text query into structured search parameters
 * (city, type, price range, etc.). The optional $context flag switches the
 * underlying system prompt between the customer marketplace and the owner
 * dashboard surface.
 */
interface AiSearchServiceInterface
{
    /**
     * Parse a natural language query into structured search parameters.
     *
     * @param  string|null  $displayCurrency  ISO-4217 of the visitor's display currency for FCFA conversion hints.
     * @param  'customer'|'owner'  $context  Selects the system prompt surface.
     * @return array<string, mixed>
     */
    public function parse(string $query, ?string $displayCurrency = null, string $context = 'customer'): array;
}

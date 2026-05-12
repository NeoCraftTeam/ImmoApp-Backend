<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for natural-language and image-based ad search parsing.
 *
 * Implementations transform a free-text query or an uploaded image
 * into structured search parameters (city, type, price range, etc.).
 */
interface AiSearchServiceInterface
{
    /**
     * Parse a natural language query into structured search parameters.
     *
     * @return array<string, mixed>
     */
    public function parse(string $query): array;

    /**
     * Parse a property image into structured search parameters.
     *
     * @return array<string, mixed>
     */
    public function parseFromImage(string $base64Image, string $mimeType = 'image/jpeg'): array;
}

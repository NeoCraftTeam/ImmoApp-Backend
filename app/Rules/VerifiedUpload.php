<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\UploadedFileInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Content-aware upload guard for endpoints that accept a mix of file types
 * (images, PDFs, office documents) on a single field.
 *
 * Complements Laravel's `mimes:` allowlist with real content inspection: the
 * dangerous-filename guard runs for every file, and the matching deep check
 * (raster parse, %PDF- header, office magic bytes) runs for each recognised
 * content MIME. Exotic-but-allowed types the inspector does not deep-parse
 * (e.g. HEIC, XLSX) still pass through the filename guard, so hardening this
 * rule never rejects a type the request already permits.
 */
final class VerifiedUpload implements ValidationRule
{
    private const array RASTER_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private const array OFFICE_DOCUMENT_MIMES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            $fail('Le fichier téléversé est invalide.');

            return;
        }

        try {
            UploadedFileInspector::rejectDangerousFilename($value->getClientOriginalName());
            $this->assertContentMatchesDetectedType($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }

    /**
     * Runs the deep parse matching the file's real content MIME. Types the
     * inspector does not deep-parse (e.g. HEIC, XLSX) are intentionally left
     * to the filename guard so the rule never rejects a permitted type.
     */
    private function assertContentMatchesDetectedType(UploadedFile $file): void
    {
        $mime = UploadedFileInspector::detectContentMime($file);

        if (in_array($mime, self::RASTER_IMAGE_MIMES, true)) {
            UploadedFileInspector::assertSafeRasterImage($file);
        } elseif ($mime === 'application/pdf') {
            UploadedFileInspector::assertSafePdf($file);
        } elseif (in_array($mime, self::OFFICE_DOCUMENT_MIMES, true)) {
            UploadedFileInspector::assertSafeOfficeDocument($file);
        }
    }
}

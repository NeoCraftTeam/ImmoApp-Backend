<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Server-side upload hardening: finfo MIME, magic bytes, safe storage extensions.
 */
final class UploadedFileInspector
{
    /** Extensions that must never appear in stored object keys or original names. */
    private const array FORBIDDEN_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar',
        'cgi', 'pl', 'asp', 'aspx', 'jsp', 'jspx', 'htaccess', 'htpasswd',
        'svg', 'shtml', 'exe', 'dll', 'sh', 'bash', 'cmd', 'bat', 'com',
    ];

    private const array RASTER_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private const array DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Detect MIME from file contents (finfo), not from the client Content-Type header.
     */
    public static function detectContentMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false || !is_readable($path)) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);

        return is_string($detected) ? strtolower(trim($detected)) : '';
    }

    /**
     * Reject polyglot / non-image payloads disguised as images (e.g. PHP in a .jpg).
     */
    public static function assertSafeRasterImage(UploadedFile $file): void
    {
        self::rejectDangerousFilename($file->getClientOriginalName());

        $path = $file->getRealPath();
        if ($path === false || @getimagesize($path) === false) {
            throw new InvalidArgumentException('Le fichier doit être une image valide (JPEG, PNG, GIF ou WebP).');
        }

        $mime = self::detectContentMime($file);
        if (!in_array($mime, self::RASTER_IMAGE_MIMES, true)) {
            throw new InvalidArgumentException('Type de fichier image non autorisé.');
        }
    }

    /**
     * Require a real PDF header (%PDF-) to block PHP/HTML polyglots named .pdf.
     */
    public static function assertSafePdf(UploadedFile $file): void
    {
        self::rejectDangerousFilename($file->getClientOriginalName());

        $path = $file->getRealPath();
        if ($path === false || !is_readable($path)) {
            throw new InvalidArgumentException('Le fichier PDF est invalide.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Le fichier PDF est invalide.');
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw new InvalidArgumentException('Le fichier doit être un PDF valide.');
        }

        $mime = self::detectContentMime($file);
        if ($mime !== '' && $mime !== 'application/pdf' && !str_starts_with($mime, 'application/pdf')) {
            throw new InvalidArgumentException('Le fichier doit être un PDF valide.');
        }
    }

    /**
     * Office Open XML (.docx) and legacy .doc checks (lightweight magic bytes).
     */
    public static function assertSafeOfficeDocument(UploadedFile $file): void
    {
        self::rejectDangerousFilename($file->getClientOriginalName());

        $mime = self::detectContentMime($file);
        if (!in_array($mime, self::DOCUMENT_MIMES, true)) {
            throw new InvalidArgumentException('Type de document non autorisé.');
        }

        if ($mime === 'application/pdf') {
            self::assertSafePdf($file);

            return;
        }

        $path = $file->getRealPath();
        if ($path === false || !is_readable($path)) {
            throw new InvalidArgumentException('Le document est invalide.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Le document est invalide.');
        }

        $header = fread($handle, 4);
        fclose($handle);

        // DOCX/XLSX/PPTX are ZIP archives (PK\x03\x04)
        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            if ($header !== "PK\x03\x04") {
                throw new InvalidArgumentException('Le fichier Word (.docx) est invalide.');
            }

            return;
        }

        // Legacy .doc (OLE compound document) — only MIME remaining after pdf/docx early returns
        if ($header !== "\xD0\xCF\x11\xE0") {
            throw new InvalidArgumentException('Le fichier Word (.doc) est invalide.');
        }
    }

    /**
     * Map a verified MIME type to a safe storage extension (never trust client extension).
     */
    public static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'audio/webm' => 'webm',
            'video/webm' => 'webm',
            'audio/mp4', 'video/mp4' => 'm4a',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/x-m4a', 'audio/m4a' => 'm4a',
            default => 'bin',
        };
    }

    /**
     * Strip path components, null bytes, and cap length for display metadata.
     */
    public static function sanitizeDisplayFilename(string $filename): string
    {
        $clean = str_replace("\0", '', $filename);
        $base = basename($clean);
        $trimmed = trim($base);

        if (in_array($trimmed, ['', '.', '..'], true)) {
            return 'file';
        }

        return mb_substr($trimmed, 0, 255);
    }

    public static function rejectDangerousFilename(string $filename): void
    {
        if (str_contains($filename, "\0")) {
            throw new InvalidArgumentException('Nom de fichier invalide.');
        }

        $lower = strtolower($filename);

        foreach (self::FORBIDDEN_EXTENSIONS as $ext) {
            if (preg_match('/\.'.preg_quote($ext, '/').'(\.|$)/i', $lower) === 1) {
                throw new InvalidArgumentException('Extension de fichier interdite.');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Conversation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Handles file upload and signed URL generation for chat attachments.
 *
 * Files are stored in Cloudflare R2 under chats/{conversation_uuid}/{uuid}.{ext}.
 * Direct R2 paths are never exposed — only signed URLs with a 24-hour TTL.
 */
final readonly class AttachmentService
{
    /** Real MIME types allowed for images. */
    private const array IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** Real MIME types allowed for documents. */
    private const array DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Upload a file to R2 and return the attachment descriptor array.
     *
     * @return array{url: string, signed_url: string, original_name: string, mime_type: string, size: int, type: string}
     *
     * @throws InvalidArgumentException if MIME type or size is invalid
     */
    public function upload(UploadedFile $file, Conversation $conversation): array
    {
        $mime = $file->getMimeType() ?: '';
        $type = $this->resolveType($mime, (int) $file->getSize());

        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension();
        $prefix = (string) config('chat.attachment_prefix', 'chats');
        $path = "{$prefix}/{$conversation->id}/{$uuid}.{$ext}";

        Storage::disk('r2')->put($path, (string) file_get_contents($file->getRealPath()), 'private');

        $signedUrl = $this->getSignedUrl($path);

        return [
            'url' => $path,
            'signed_url' => $signedUrl,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'type' => $type,
        ];
    }

    /**
     * Generate a signed URL with 24-hour expiry for an R2-stored file.
     * Never expose the raw storage path to clients.
     *
     * @param  string  $path  Internal R2 storage path
     */
    public function getSignedUrl(string $path): string
    {
        $ttl = (int) config('chat.signed_url_ttl_hours', 24);

        return (string) Storage::disk('r2')->temporaryUrl(
            $path,
            now()->addHours($ttl),
        );
    }

    /**
     * Resolve whether a file is an image or a document, and validate size.
     *
     * @throws InvalidArgumentException on invalid MIME type or excessive file size
     */
    private function resolveType(string $mime, int $sizeBytes): string
    {
        $imageLimitBytes = (int) config('chat.uploads.image_max_mb', 10) * 1024 * 1024;
        $fileLimitBytes = (int) config('chat.uploads.file_max_mb', 20) * 1024 * 1024;

        if (in_array($mime, self::IMAGE_MIMES, true)) {
            if ($sizeBytes > $imageLimitBytes) {
                throw new InvalidArgumentException('Image too large (max 10 MB).');
            }

            return 'image';
        }

        if (in_array($mime, self::DOCUMENT_MIMES, true)) {
            if ($sizeBytes > $fileLimitBytes) {
                throw new InvalidArgumentException('Document too large (max 20 MB).');
            }

            return 'file';
        }

        throw new InvalidArgumentException("Unsupported file type: {$mime}");
    }
}

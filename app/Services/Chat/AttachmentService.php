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
    /** Real MIME types allowed for images (GIF is enabled for modern UX parity). */
    private const array IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** Real MIME types allowed for documents. */
    private const array DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Real MIME types allowed for voice notes. */
    private const array AUDIO_MIMES = [
        'audio/webm',
        // WebM is often classified as video for browser MediaRecorder output.
        'video/webm',
        'audio/mp4',
        'audio/mpeg',
        'audio/mp3',
        'audio/x-m4a',
        'audio/m4a',
        'audio/ogg',
        'audio/wav',
        // Safari MediaRecorder stores AAC in an MPEG-4 container — PHP often reports video/mp4.
        'video/mp4',
    ];

    /**
     * Refresh signed URLs for every attachment row (R2 paths stay stable; URLs expire).
     *
     * @param  array<int, array<string, mixed>>|null  $attachments
     * @return array<int, array<string, mixed>>|null
     */
    public function refreshSignedUrlsInAttachments(?array $attachments): ?array
    {
        if ($attachments === null) {
            return null;
        }

        return array_map(function (array $row): array {
            $path = $row['url'] ?? null;
            if (!is_string($path) || $path === '') {
                return $row;
            }

            try {
                $row['signed_url'] = $this->getSignedUrl($path);
            } catch (\Throwable) {
                // keep existing signed_url
            }

            return $row;
        }, $attachments);
    }

    /**
     * Upload a file to R2 and return the attachment descriptor array.
     *
     * @return array{url: string, signed_url: string, original_name: string, mime_type: string, size: int, type: string}
     *
     * @throws InvalidArgumentException if MIME type or size is invalid
     */
    public function upload(UploadedFile $file, Conversation $conversation): array
    {
        $mime = $this->normalizeDetectedMime($file);
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
     * Verify that an attachment storage path belongs to the given conversation.
     * Stops a malicious sender from referencing attachments uploaded under
     * another conversation prefix (e.g. someone else's chat folder on R2).
     *
     * The expected layout is `{prefix}/{conversation_uuid}/{file_uuid}.{ext}`.
     * We refuse anything that doesn't start with the conversation's prefix.
     */
    public function belongsToConversation(string $url, string $conversationId): bool
    {
        $prefix = (string) config('chat.attachment_prefix', 'chats');
        $expected = "{$prefix}/{$conversationId}/";

        return str_starts_with($url, $expected);
    }

    /**
     * Resolve whether a file is an image, a document, or an audio voice note,
     * and validate size for the resolved type.
     *
     * @throws InvalidArgumentException on invalid MIME type or excessive file size
     */
    private function resolveType(string $mime, int $sizeBytes): string
    {
        $imageLimitBytes = (int) config('chat.uploads.image_max_mb', 10) * 1024 * 1024;
        $fileLimitBytes = (int) config('chat.uploads.file_max_mb', 20) * 1024 * 1024;
        $audioLimitBytes = (int) config('chat.uploads.audio_max_mb', 5) * 1024 * 1024;

        if (in_array($mime, self::IMAGE_MIMES, true)) {
            if ($sizeBytes > $imageLimitBytes) {
                throw new InvalidArgumentException('Image too large (max 10 MB).');
            }

            return 'image';
        }

        if (in_array($mime, self::AUDIO_MIMES, true)) {
            if ($sizeBytes > $audioLimitBytes) {
                throw new InvalidArgumentException('Voice note too large (max 5 MB).');
            }

            return 'audio';
        }

        if (in_array($mime, self::DOCUMENT_MIMES, true)) {
            if ($sizeBytes > $fileLimitBytes) {
                throw new InvalidArgumentException('Document too large (max 20 MB).');
            }

            return 'file';
        }

        throw new InvalidArgumentException("Unsupported file type: {$mime}");
    }

    /**
     * PHP finfo + browsers often label WebM voice notes as {@see video/webm},
     * or as octet-stream. Normalize so validation matches what we store and return.
     */
    private function normalizeDetectedMime(UploadedFile $file): string
    {
        $mime = strtolower(trim($file->getMimeType() ?: ''));
        $ext = strtolower($file->getClientOriginalExtension());

        if ($mime === 'video/webm') {
            return 'audio/webm';
        }

        if ($mime === '' || $mime === 'application/octet-stream') {
            return match ($ext) {
                'webm' => 'audio/webm',
                'mp4', 'm4a' => 'audio/mp4',
                'mp3', 'mpga' => 'audio/mpeg',
                'ogg' => 'audio/ogg',
                'wav' => 'audio/wav',
                default => $mime,
            };
        }

        return $mime;
    }
}

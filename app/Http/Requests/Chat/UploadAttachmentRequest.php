<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a file attachment upload.
 * Real MIME type validation is performed in AttachmentService (not just extension).
 */
final class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480', // 20 MB max at form level; AttachmentService enforces per-type limits
                // GIF (modern UX), audio (voice notes) added — AttachmentService validates real MIME type.
                'mimes:jpeg,jpg,png,webp,gif,pdf,doc,docx,webm,mp4,mp3,mpga,ogg,wav,m4a',
            ],
        ];
    }
}

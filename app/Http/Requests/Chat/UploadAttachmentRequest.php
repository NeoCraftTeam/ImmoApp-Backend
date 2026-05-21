<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Support\UploadedFileInspector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
                // Extension whitelist (client name); AttachmentService re-checks finfo + magic bytes.
                'extensions:jpg,jpeg,png,webp,gif,pdf,doc,docx,webm,mp4,mp3,mpga,ogg,wav,m4a',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $file = $this->file('file');
            if ($file === null) {
                return;
            }

            try {
                UploadedFileInspector::rejectDangerousFilename($file->getClientOriginalName());
            } catch (\InvalidArgumentException $exception) {
                $v->errors()->add('file', $exception->getMessage());
            }
        });
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'file.required' => 'Veuillez sélectionner un fichier.',
            'file.extensions' => 'Format de fichier non autorisé.',
            'file.max' => 'Le fichier ne peut pas dépasser 20 Mo.',
        ];
    }
}

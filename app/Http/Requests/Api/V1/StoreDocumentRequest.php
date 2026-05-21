<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\VerifiedImageUpload;
use App\Rules\VerifiedPdfUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:permit,insurance,title,receipt,other'],
            'name' => ['required', 'string', 'max:255'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:10240',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $file = $this->file('file');
            if (!$file instanceof UploadedFile) {
                return;
            }

            $mime = strtolower((string) $file->getMimeType());
            if (str_starts_with($mime, 'image/')) {
                /** @phpstan-ignore argument.type */
                (new VerifiedImageUpload)->validate('file', $file, fn (string $message) => $v->errors()->add('file', $message));

                return;
            }

            if ($mime === 'application/pdf') {
                /** @phpstan-ignore argument.type */
                (new VerifiedPdfUpload)->validate('file', $file, fn (string $message) => $v->errors()->add('file', $message));
            }
        });
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'file.required' => 'Veuillez joindre un fichier.',
            'file.mimes' => 'Formats acceptés : PDF, JPEG, PNG ou WebP.',
            'file.mimetypes' => 'Le type de fichier n\'est pas autorisé.',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo.',
        ];
    }
}

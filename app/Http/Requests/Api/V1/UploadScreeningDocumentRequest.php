<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ScreeningDocumentType;
use App\Rules\VerifiedUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UploadScreeningDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::in(array_column(ScreeningDocumentType::cases(), 'value'))],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf', new VerifiedUpload],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

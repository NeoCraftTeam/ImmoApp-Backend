<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DisputeEvidenceType;
use App\Rules\VerifiedUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDisputeEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DisputeEvidenceType::class)],
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:jpg,jpeg,png,webp,heic,pdf,doc,docx,xlsx',
                new VerifiedUpload,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ScreeningDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_email' => ['required', 'email', 'max:255'],
            'required_documents' => ['required', 'array', 'min:1', 'max:8'],
            'required_documents.*' => ['required', 'string', Rule::in(array_column(ScreeningDocumentType::cases(), 'value'))],
            'landlord_notes' => ['nullable', 'string', 'max:2000'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ];
    }
}

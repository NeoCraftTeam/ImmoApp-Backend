<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLeaseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_name' => ['sometimes', 'string', 'max:255'],
            'tenant_phone' => ['sometimes', 'string', 'max:50'],
            'tenant_email' => ['nullable', 'email', 'max:255'],
            'tenant_id_number' => ['nullable', 'string', 'max:100'],
            'unit_reference' => ['nullable', 'string', 'max:100'],
            'special_conditions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

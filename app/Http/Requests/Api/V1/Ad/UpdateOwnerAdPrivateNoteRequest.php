<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOwnerAdPrivateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_property_owner' => ['required', 'boolean'],
            'owner_name' => ['nullable', 'required_if:is_property_owner,false', 'string', 'max:150'],
            'owner_address' => ['nullable', 'required_if:is_property_owner,false', 'string', 'max:500'],
            'owner_phone' => ['nullable', 'required_if:is_property_owner,false', 'string', 'max:40'],
            'owner_email' => ['nullable', 'email:rfc', 'max:254'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}

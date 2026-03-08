<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FlutterwaveVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'tx_ref' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'tx_ref.required' => 'La référence de transaction est requise.',
            'tx_ref.string' => 'La référence de transaction doit être une chaîne.',
        ];
    }
}

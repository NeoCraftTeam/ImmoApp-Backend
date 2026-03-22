<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role?->value === 'admin';
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'reason.required' => 'Le motif du remboursement est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 5 caractères.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
        ];
    }
}

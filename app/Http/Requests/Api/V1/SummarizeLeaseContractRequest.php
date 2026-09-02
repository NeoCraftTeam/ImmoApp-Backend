<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SummarizeLeaseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'string', 'max:30'],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'special_conditions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

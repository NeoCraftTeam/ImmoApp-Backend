<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class RenewLeaseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // How many additional months to add to the existing
            // `lease_end`. The lease's existing duration is preserved
            // (we don't reset `lease_duration_months`, only extend).
            'extend_months' => ['required', 'integer', 'min:1', 'max:120'],
            // Optional rent increase / decrease applied to the renewed
            // term. Pass the same `monthly_rent` to leave it unchanged.
            'monthly_rent' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}

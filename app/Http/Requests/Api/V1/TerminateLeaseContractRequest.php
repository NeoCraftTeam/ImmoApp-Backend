<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class TerminateLeaseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Human-readable reason captured for the audit trail.
            // Required because terminating a lease is a destructive
            // action and we want a paper trail in case of dispute.
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}

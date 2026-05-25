<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DisputeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionDisputeRequest extends FormRequest
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
            'status' => ['required', Rule::enum(DisputeStatus::class)],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function targetStatus(): DisputeStatus
    {
        return DisputeStatus::from($this->string('status')->toString());
    }
}

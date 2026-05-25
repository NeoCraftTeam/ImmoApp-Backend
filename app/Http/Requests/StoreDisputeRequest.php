<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DisputeType;
use App\Models\Dispute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Dispute::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DisputeType::class)],
            // respondent_id is optional: when missing, the service derives it
            // from the provided context (ad_id → ad.user_id, lease_id → other
            // party, payment_id → recipient). At least one of these must be
            // present so we can identify a counterparty.
            'respondent_id' => [
                'required_without_all:ad_id,lease_id,payment_id',
                'nullable',
                'uuid',
                'exists:users,id',
            ],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'amount_claimed' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'ad_id' => ['nullable', 'uuid', 'exists:ad,id'],
            'lease_id' => ['nullable', 'uuid', 'exists:lease_contracts,id'],
            'payment_id' => ['nullable', 'uuid', 'exists:payments,id'],
        ];
    }
}

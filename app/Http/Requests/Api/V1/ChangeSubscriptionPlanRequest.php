<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Validates the target of an agency plan change (upgrade / downgrade). Both
 * endpoints share identical rules — an existing plan and a supported billing
 * period. Extracted from the inline validation in
 * SubscriptionController::upgrade()/downgrade(); ownership and the "already on
 * this plan" check stay in the controller.
 */
final class ChangeSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'exists:subscription_plans,id'],
            'billing_period' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
        ];
    }
}

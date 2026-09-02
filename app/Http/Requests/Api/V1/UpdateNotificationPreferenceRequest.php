<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'new_viewing_request' => ['sometimes', 'boolean'],
            'viewing_confirmed' => ['sometimes', 'boolean'],
            'new_review' => ['sometimes', 'boolean'],
            'payment_received' => ['sometimes', 'boolean'],
            'ad_expired' => ['sometimes', 'boolean'],
            'lease_expiring' => ['sometimes', 'boolean'],
            'new_message' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
        ];
    }
}

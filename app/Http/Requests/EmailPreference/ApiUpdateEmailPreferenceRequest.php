<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailPreference;

use Illuminate\Foundation\Http\FormRequest;

final class ApiUpdateEmailPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ad_updates' => ['sometimes', 'boolean'],
            'search_alerts' => ['sometimes', 'boolean'],
            'subscription_updates' => ['sometimes', 'boolean'],
            'survey_notifications' => ['sometimes', 'boolean'],
            'admin_notifications' => ['sometimes', 'boolean'],
            'welcome_emails' => ['sometimes', 'boolean'],
        ];
    }
}

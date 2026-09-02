<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailPreference;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateEmailPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ad_updates' => ['boolean'],
            'search_alerts' => ['boolean'],
            'subscription_updates' => ['boolean'],
            'survey_notifications' => ['boolean'],
            'admin_notifications' => ['boolean'],
            'welcome_emails' => ['boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a message send request.
 * Body is required when no attachments are provided.
 */
final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'type' => ['nullable', 'string', 'in:text,image,file'],
            'reply_to_id' => ['nullable', 'uuid', Rule::exists('messages', 'id')->whereNull('deleted_at')],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.url' => ['required_with:attachments', 'string', 'max:500'],
            'attachments.*.signed_url' => ['required_with:attachments', 'string', 'url', 'max:2048'],
            'attachments.*.original_name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.mime_type' => ['required_with:attachments', 'string', 'in:image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'attachments.*.size' => ['required_with:attachments', 'integer', 'min:1', 'max:20971520'],
            'attachments.*.type' => ['required_with:attachments', 'string', 'in:image,file'],
        ];
    }
}

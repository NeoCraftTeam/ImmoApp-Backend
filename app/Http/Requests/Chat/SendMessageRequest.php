<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

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
            'body'        => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'type'        => ['nullable', 'string', 'in:text,image,file'],
            'reply_to_id' => ['nullable', 'uuid', 'exists:messages,id'],
            'attachments' => ['nullable', 'array'],
        ];
    }
}

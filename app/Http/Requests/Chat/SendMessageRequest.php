<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate a message send request.
 * Body is required when no attachments are provided (non-E2EE only).
 *
 * End-to-end (client-sealed): server stores AES-GCM ciphertext only; plain `body` and
 * `attachments` are disallowed.
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
        $conversationId = (string) $this->route('uuid');

        return [
            'is_client_sealed' => ['sometimes', 'boolean'],
            'e2ee_ciphertext_b64' => ['required_if:is_client_sealed,true', 'string', 'max:65536'],
            'e2ee_iv_b64' => ['required_if:is_client_sealed,true', 'string', 'max:64'],
            'e2ee_wrapped_keys' => ['nullable', 'array'],
            'e2ee_wrapped_keys.tenant' => ['required_with:e2ee_wrapped_keys', 'string', 'max:2048'],
            'e2ee_wrapped_keys.landlord' => ['required_with:e2ee_wrapped_keys', 'string', 'max:2048'],
            'body' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn () => !$this->boolean('is_client_sealed') && !$this->filled('attachments')),
            ],
            'type' => ['nullable', 'string', 'in:text,image,file,audio'],
            'reply_to_id' => [
                'nullable',
                'uuid',
                Rule::exists('messages', 'id')
                    ->whereNull('deleted_at')
                    ->where('conversation_id', $conversationId),
            ],
            'attachments' => ['nullable', 'array', 'max:5', Rule::prohibitedIf(fn () => $this->boolean('is_client_sealed'))],
            'attachments.*.url' => ['required_with:attachments', 'string', 'max:500'],
            'attachments.*.signed_url' => ['required_with:attachments', 'string', 'url', 'max:2048'],
            'attachments.*.original_name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.mime_type' => ['required_with:attachments', 'string', 'in:image/jpeg,image/png,image/webp,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,audio/webm,audio/mp4,audio/mpeg,audio/mp3,audio/x-m4a,audio/m4a,audio/ogg,audio/wav,video/mp4'],
            'attachments.*.size' => ['required_with:attachments', 'integer', 'min:1', 'max:20971520'],
            'attachments.*.type' => ['required_with:attachments', 'string', 'in:image,file,audio'],
            'attachments.*.audio_duration_ms' => ['nullable', 'integer', 'min:100', 'max:120000'],
            'attachments.*.audio_waveform_peaks' => ['nullable', 'array', 'max:120'],
            'attachments.*.audio_waveform_peaks.*' => ['numeric', 'between:0,1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (!$this->boolean('is_client_sealed')) {
                return;
            }
            if ($this->filled('body') && $this->string('body')->toString() !== '') {
                $v->errors()->add('body', 'Plaintext body must not be sent when is_client_sealed is true.');
            }
            $type = $this->input('type');
            if (!in_array($type, [null, '', 'text'], true)) {
                $v->errors()->add('type', 'E2EE messages must use type text.');
            }
        });
    }
}

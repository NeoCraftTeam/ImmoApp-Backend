<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * RSA public key in PEM format (WebCrypto SPKI wrapped as PEM is accepted).
 */
final class UpdateChatE2eeIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'public_key_pem' => ['required', 'string', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $pem = $this->string('public_key_pem')->toString();
            if ($pem === '') {
                return;
            }
            if (openssl_pkey_get_public($pem) === false) {
                $v->errors()->add(
                    'public_key_pem',
                    'The public key is not a valid PEM-encoded RSA public key.',
                );
            }
        });
    }
}

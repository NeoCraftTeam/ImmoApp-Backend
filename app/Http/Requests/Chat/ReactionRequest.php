<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an emoji reaction add/remove payload. Emoji length is capped to
 * fit a single visual emoji (incl. surrogate pairs / combining marks).
 */
final class ReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:16'],
        ];
    }
}

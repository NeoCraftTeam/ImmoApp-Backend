<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:permit,insurance,title,receipt,other'],
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\VerifiedImageUpload;
use Illuminate\Foundation\Http\FormRequest;

final class AgencyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                new VerifiedImageUpload,
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuarterRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'name' => [
                    'required', 'string', 'max:255',
                    Rule::unique('quarter', 'name')->where('city_id', $this->input('city_id')),
                ],
                'city_id' => ['required', 'uuid', 'exists:city,id'],
            ];
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $routeQuarter = $this->route('quarter');
            $quarterId = is_object($routeQuarter) ? $routeQuarter->id : $routeQuarter;

            return [
                'name' => [
                    'sometimes', 'string', 'max:255',
                    Rule::unique('quarter', 'name')
                        ->where('city_id', $this->input('city_id') ?? $this->route('quarter')?->city_id)
                        ->ignore($quarterId),
                ],
                'city_id' => ['sometimes', 'uuid', 'exists:city,id'],
            ];
        }

        return [];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du quartier est obligatoire.',
            'name.max' => 'Le nom du quartier ne doit pas dépasser 255 caractères.',
            'name.unique' => 'Ce quartier existe déjà dans cette ville.',
            'city_id.required' => 'La ville est obligatoire.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AnonymousSurveySubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Survey $survey */
        $survey = $this->route('survey');

        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => [
                'required',
                'uuid',
                Rule::exists('survey_questions', 'id')->where('survey_id', $survey->id),
            ],
            'answers.*.answer' => ['required', 'string', 'max:1000'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'answers.required' => 'Veuillez répondre à au moins une question.',
            'answers.*.question_id.exists' => 'Une question soumise n\'appartient pas à ce sondage.',
            'answers.*.answer.required' => 'Chaque réponse ne peut pas être vide.',
            'answers.*.answer.max' => 'Une réponse ne peut pas dépasser 1000 caractères.',
        ];
    }
}

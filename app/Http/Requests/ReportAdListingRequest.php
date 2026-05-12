<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AdReportReason;
use App\Enums\AdReportScamReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportAdListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $isScamReason = fn (): bool => $this->input('reason') === AdReportReason::SCAM->value;
        $isAskedOffPlatformPayment = fn (): bool => $isScamReason()
            && $this->input('scam_reason') === AdReportScamReason::ASKED_OFF_PLATFORM_PAYMENT->value;
        $isOtherReason = fn (): bool => $this->input('reason') === AdReportReason::OTHER->value;

        return [
            'reason' => ['required', Rule::enum(AdReportReason::class)],
            'scam_reason' => [
                'nullable',
                Rule::requiredIf($isScamReason),
                Rule::prohibitedIf(fn (): bool => !$isScamReason()),
                Rule::enum(AdReportScamReason::class),
            ],
            'payment_methods' => [
                'nullable',
                'array',
                Rule::requiredIf($isAskedOffPlatformPayment),
                Rule::prohibitedIf(fn (): bool => !$isAskedOffPlatformPayment()),
                'min:1',
                'max:7',
            ],
            'payment_methods.*' => [
                'string',
                'distinct:strict',
                Rule::in([
                    'bank_transfer',
                    'card',
                    'cash',
                    'paypal',
                    'moneygram',
                    'western_union',
                    'other',
                ]),
            ],
            'description' => [
                'nullable',
                'string',
                'min:10',
                'max:2000',
                Rule::requiredIf($isOtherReason),
                Rule::prohibitedIf(fn (): bool => !$isOtherReason()),
            ],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'reason.required' => 'Veuillez choisir le motif principal du signalement.',
            'scam_reason.required' => 'Veuillez choisir la raison de l\'arnaque.',
            'payment_methods.required' => 'Veuillez préciser au moins une méthode de paiement.',
            'payment_methods.min' => 'Veuillez choisir au moins une méthode de paiement.',
            'payment_methods.max' => 'Le nombre de méthodes de paiement sélectionnées est trop élevé.',
            'description.required' => 'Veuillez décrire le problème.',
            'description.min' => 'Veuillez décrire le problème avec au moins 10 caractères.',
        ];
    }
}

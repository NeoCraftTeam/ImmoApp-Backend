<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\Concerns\EnsuresCreditPurchasePassesTurnstile;
use App\Services\Payment\PaymentMethodGateService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a payment-initiation request for any gateway.
 *
 * Mobile money (Mobile Money / Orange Money) routes to GeniusPay hosted
 * checkout; `card` routes to Stripe in-page Elements / saved-card reuse.
 */
class InitiatePaymentRequest extends FormRequest
{
    use EnsuresCreditPurchasePassesTurnstile;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:subscription,credit'],
            'payment_method' => ['nullable', 'string', 'in:mobile_money,orange_money,card'],
            'phone_number' => ['nullable', 'string', 'regex:/^\\+?[0-9\\s\\-]{7,20}$/'],
            'agency_id' => ['required_if:type,subscription', 'nullable', 'uuid', 'exists:agency,id'],
            'plan_id' => [
                'required_if:type,subscription',
                'required_if:type,credit',
                'nullable',
                'uuid',
            ],
            'period' => ['required_if:type,subscription', 'nullable', 'string', 'in:monthly,yearly'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            // URL de retour après paiement. Deep-link natif mobile
            // (keyhome://…, exp://…) ou URL web whitelistée — validée et,
            // pour les deep-links, enveloppée dans le pont HTTPS par
            // PaymentService. Sans elle, on retombe sur la page web de retour.
            'callback_url' => ['nullable', 'string', 'max:2048'],
            // Stripe-only options. Silently ignored when `payment_method`
            // is anything other than `card`. The `withValidator()` hook
            // surfaces a French error message in that case.
            'save_payment_method' => ['nullable', 'boolean'],
            'payment_method_id' => ['nullable', 'string', 'regex:/^pm_[A-Za-z0-9_]+$/', 'max:255'],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * Reject payment methods that an admin has disabled via Filament.
     * Runs after the basic `in:` rule so we know the value is one of the
     * known cases before we hit the gate service.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // Must run even when `payment_method` is omitted (defaults on the server).
            if ($this->input('type') === 'credit') {
                $this->enforceTurnstileForCreditPurchase($v);
            }

            $method = $this->input('payment_method');
            if (!is_string($method) || $method === '') {
                return;
            }
            $enum = PaymentMethod::tryFrom($method);
            if ($enum === null) {
                return;
            }
            /** @var PaymentMethodGateService $gate */
            $gate = app(PaymentMethodGateService::class);
            if (!$gate->isEnabled($enum)) {
                $v->errors()->add(
                    'payment_method',
                    sprintf('Le moyen de paiement « %s » est temporairement indisponible.', $enum->label()),
                );
            }

            // Stripe-specific guards: `save_payment_method` and
            // `payment_method_id` only make sense for the Card flow.
            if ($enum !== PaymentMethod::CARD) {
                if ($this->boolean('save_payment_method')) {
                    $v->errors()->add(
                        'save_payment_method',
                        'L\'enregistrement de la carte n\'est disponible que pour les paiements par carte bancaire.',
                    );
                }
                $paymentMethodId = $this->input('payment_method_id');
                if (is_string($paymentMethodId) && $paymentMethodId !== '') {
                    $v->errors()->add(
                        'payment_method_id',
                        'La réutilisation d\'une carte enregistrée n\'est disponible que pour les paiements par carte bancaire.',
                    );
                }
            }
        });
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de paiement est requis.',
            'type.in' => 'Le type doit être subscription ou credit.',
            'phone_number.regex' => 'Le format du numéro de téléphone est invalide.',
            'agency_id.required_if' => 'L\'agence est requise pour un abonnement.',
            'agency_id.exists' => 'L\'agence spécifiée est introuvable.',
            'plan_id.required_if' => 'Le plan est requis pour ce type de paiement.',
            'period.required_if' => 'La période est requise pour un abonnement.',
            'period.in' => 'La période doit être monthly ou yearly.',
            'payment_method_id.regex' => 'L\'identifiant de carte enregistrée est invalide.',
        ];
    }
}

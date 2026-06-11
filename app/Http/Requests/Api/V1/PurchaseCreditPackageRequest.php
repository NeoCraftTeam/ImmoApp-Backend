<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\Concerns\EnsuresCreditPurchasePassesTurnstile;
use App\Services\Payment\PaymentMethodGateService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the credit-pack purchase request.
 *
 * Accepts two distinct sub-flows that both ultimately route to a
 * `PaymentGatewayInterface::initiate()` call:
 *  - Mobile-money hosted-checkout: requires `payment_method` ∈ mobile money
 *    family (or unset → default `mobile_money`).
 *  - Stripe in-page Elements / saved-card reuse: `payment_method=card`,
 *    optionally with `save_payment_method=true` and/or `payment_method_id`.
 *
 * `payment_method_id` matches `pm_*` to keep raw input strict ; the
 * controller still cross-checks ownership against the Stripe Customer
 * before charging (defence in depth).
 */
class PurchaseCreditPackageRequest extends FormRequest
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
            'callback_url' => ['nullable', 'string', 'url', 'max:2048'],
            'payment_method' => ['nullable', 'string', 'in:mobile_money,orange_money,card'],
            'save_payment_method' => ['nullable', 'boolean'],
            'payment_method_id' => ['nullable', 'string', 'regex:/^pm_[A-Za-z0-9_]+$/', 'max:255'],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * Reject payment methods that an admin has disabled via Filament.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // This request is credits-only — enforce before optional `payment_method` checks.
            $this->enforceTurnstileForCreditPurchase($v);

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
                if (is_string($this->input('payment_method_id')) && $this->input('payment_method_id') !== '') {
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
            'callback_url.url' => 'L\'URL de retour est invalide.',
            'payment_method.in' => 'Le moyen de paiement sélectionné n\'est pas pris en charge.',
            'payment_method_id.regex' => 'L\'identifiant de carte enregistrée est invalide.',
        ];
    }
}

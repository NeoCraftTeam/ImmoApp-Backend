<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\Concerns\EnsuresCreditPurchasePassesTurnstile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * FormRequest jetable exposant le trait pour le tester en isolation.
 */
function makeTurnstileGateRequest(bool $withSession): FormRequest
{
    $request = new class extends FormRequest
    {
        use EnsuresCreditPurchasePassesTurnstile;

        public function runGate(Validator $validator): void
        {
            $this->enforceTurnstileForCreditPurchase($validator);
        }
    };

    if ($withSession) {
        $request->setLaravelSession(app('session')->driver('array'));
    }

    return $request;
}

beforeEach(function (): void {
    // Secret réel → isConfigured() vrai ; aucun token n'est fourni →
    // verify() renvoie false sans appel HTTP. Ainsi seul le gating
    // `hasSession()` décide si l'erreur Turnstile est ajoutée.
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');
});

it('enforces turnstile when the request has a web session', function (): void {
    $request = makeTurnstileGateRequest(withSession: true);
    $validator = ValidatorFacade::make([], []);

    $request->runGate($validator);

    expect($validator->errors()->has('turnstile_token'))->toBeTrue();
});

it('skips turnstile when the request is stateless (mobile bearer)', function (): void {
    $request = makeTurnstileGateRequest(withSession: false);
    $validator = ValidatorFacade::make([], []);

    $request->runGate($validator);

    expect($validator->errors()->has('turnstile_token'))->toBeFalse();
});

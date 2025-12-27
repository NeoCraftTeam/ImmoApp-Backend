<?php

namespace App\Services;

use FedaPay\FedaPay;
use FedaPay\Transaction;

class FedaPayService
{
    public function __construct()
    {
        // On configure les clés dynamiquement depuis le fichier .env
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment'));
    }

    /**
     * Créer une transaction de paiement pour une annonce.
     */
    public function createPayment($amount, $user, $adId)
    {
        try {
            $key = config('services.fedapay.secret_key');
            $env = config('services.fedapay.environment', 'sandbox');

            if (! $key) {
                throw new \Exception('Clé secrète FedaPay manquante dans la configuration.');
            }

            FedaPay::setApiKey($key);
            FedaPay::setEnvironment($env);

            /** @var Transaction $transaction */
            $transaction = Transaction::create([
                'description' => "Déblocage de l'annonce #{$adId}",
                'amount' => $amount,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => config('app.email_verify_callback', config('app.url'))."/payment-success?ad_id={$adId}",
                'customer' => [
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                ],
            ]);

            /** @var \FedaPay\FedaPayObject $token */
            $token = $transaction->generateToken();

            return [
                'success' => true,
                'url' => (string) ($token->url ?? ''),
                'transaction_id' => $transaction->id,
            ];
        } catch (\Exception $e) {
            \Log::error('FedaPay Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Créer une transaction de paiement pour un abonnement d'agence.
     */
    public function createSubscriptionPayment($amount, $agency, $planId, $period = 'monthly', $callbackUrl = null)
    {
        try {
            $key = config('services.fedapay.secret_key');
            $env = config('services.fedapay.environment', 'sandbox');

            if (! $key) {
                throw new \Exception('Clé secrète FedaPay manquante dans la configuration.');
            }

            FedaPay::setApiKey($key);
            FedaPay::setEnvironment($env);

            /** @var Transaction $transaction */
            $transaction = Transaction::create([
                'description' => 'Souscription Abonnement',
                'amount' => $amount,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $callbackUrl ?? (config('app.url').'/agency/abonnement'),
                'customer' => [
                    'firstname' => $agency->name ?? 'Agence',
                    'lastname' => 'Member',
                    'email' => auth()->user()->email,
                ],
                // On passe les infos importantes dans les métadonnées
                'metadata' => [
                    'payment_type' => 'subscription',
                    'agency_id' => $agency->id,
                    'plan_id' => $planId,
                    'period' => $period, // 'monthly' or 'yearly'
                ],
            ]);

            /** @var \FedaPay\FedaPayObject $token */
            $token = $transaction->generateToken();

            return [
                'success' => true,
                'url' => (string) ($token->url ?? ''),
                'transaction_id' => $transaction->id,
            ];
        } catch (\Exception $e) {
            \Log::error('FedaPay Subscription Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use Filament\Pages\Page;

class ManageSubscription extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Mon Abonnement';

    protected static ?string $title = 'Gestion de l\'abonnement';

    protected static ?string $slug = 'abonnement';

    protected string $view = 'filament.agency.pages.manage-subscription';

    public static function getNavigationBadge(): ?string
    {
        try {
            /** @var \App\Models\Agency|null $agency */
            $agency = auth()->user()?->agency;

            return $agency && $agency->hasActiveSubscription() ? 'Actif' : null;
        } catch (\Exception) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public ?\App\Models\Subscription $subscription = null;

    public $plans;

    public string $period = 'monthly';

    public array $stats = [];

    /** Set to true while awaiting webhook confirmation after payment redirect. */
    public bool $awaitingConfirmation = false;

    public function mount(): void
    {
        /** @var \App\Models\Agency|null $agency */
        $agency = auth()->user()->agency;

        if (!$agency) {
            return;
        }

        // Vérifier si on revient d'un paiement réussi
        if (request()->query('status') === 'approved' && request()->query('id')) {
            $this->verifyPayment(request()->query('id'));
        }

        $this->subscription = $agency->getCurrentSubscription();
        $this->plans = \App\Models\SubscriptionPlan::active()->orderBy('sort_order')->get();
        $this->stats = app(\App\Services\SubscriptionService::class)->getAgencyStats($agency);

        // If there's a pending payment in session and no active subscription, start polling.
        if (!$this->subscription?->isActive() && session()->has('keyhome_pending_transaction')) {
            $this->awaitingConfirmation = true;
        } else {
            session()->forget('keyhome_pending_transaction');
        }
    }

    /**
     * Progress percentage for the active subscription (0-100).
     */
    #[\Livewire\Attributes\Computed]
    public function progress(): int
    {
        if (!$this->subscription || !$this->subscription->starts_at || !$this->subscription->ends_at) {
            return 0;
        }

        $total = $this->subscription->starts_at->diffInDays($this->subscription->ends_at);
        $elapsed = $this->subscription->starts_at->diffInDays(now());

        return $total > 0 ? (int) min(100, round(($elapsed / $total) * 100)) : 0;
    }

    protected function verifyPayment(string $transactionId): void
    {
        /** @var \App\Models\Agency|null $agency */
        $agency = auth()->user()->agency;

        if (!$agency) {
            return;
        }

        $payment = \App\Models\Payment::where('transaction_id', $transactionId)
            ->where('agency_id', $agency->id)
            ->where('status', \App\Enums\PaymentStatus::PENDING)
            ->first();

        if (!$payment) {
            return;
        }

        try {
            $paymentService = app(\App\Services\Payment\PaymentService::class);
            $payment = $paymentService->syncPaymentStatus($payment);

            if ($payment->status !== \App\Enums\PaymentStatus::SUCCESS) {
                \Illuminate\Support\Facades\Log::warning('Payment verification failed for subscription', [
                    'transaction_id' => $transactionId,
                    'agency_id' => $agency->id,
                    'status' => $payment->status->value,
                ]);

                return;
            }

            $plan = \App\Models\SubscriptionPlan::find($payment->plan_id);

            if ($plan && !$agency->hasActiveSubscription()) {
                $subscriptionService = app(\App\Services\SubscriptionService::class);
                $subscription = $subscriptionService->createSubscription($agency, $plan, $payment->period ?? 'monthly', $payment);
                $subscriptionService->activateSubscription($subscription);

                \Filament\Notifications\Notification::make()
                    ->title('Paiement réussi !')
                    ->body('Votre abonnement a été activé avec succès. Bienvenue !')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Payment verification error: '.$e->getMessage());
        }
    }

    /**
     * Poll every 5 seconds while awaitingConfirmation is true.
     * Called by wire:poll in the Blade view.
     */
    public function refreshSubscriptionStatus(): void
    {
        /** @var \App\Models\Agency|null $agency */
        $agency = auth()->user()->agency;

        if (!$agency) {
            return;
        }

        $transactionId = session('keyhome_pending_transaction');

        // Try to verify via payment gateway if we still have a pending transaction ID.
        if ($transactionId) {
            $this->verifyPayment($transactionId);
        }

        $this->subscription = $agency->getCurrentSubscription();
        $this->stats = app(\App\Services\SubscriptionService::class)->getAgencyStats($agency);

        if ($this->subscription?->isActive()) {
            $this->awaitingConfirmation = false;
            session()->forget('keyhome_pending_transaction');

            \Filament\Notifications\Notification::make()
                ->title('Abonnement activé !')
                ->body('Votre abonnement '.($this->subscription->plan->name ?? '').' est maintenant actif. Vos annonces vont être boostées.')
                ->success()
                ->send();
        }
    }

    public function cancelWaiting(): void
    {
        $this->awaitingConfirmation = false;
        session()->forget('keyhome_pending_transaction');
    }

    public function setPeriod(string $period)
    {
        $this->period = $period;
    }

    public function subscribe(string $planId)
    {
        $plan = \App\Models\SubscriptionPlan::findOrFail($planId);
        /** @var \App\Models\Agency $agency */
        $agency = auth()->user()->agency;

        $price = $this->period === 'yearly' ? $plan->price_yearly : $plan->price;

        if (!$price) {
            \Filament\Notifications\Notification::make()
                ->title('Option non disponible')
                ->body('Ce plan ne propose pas cette fréquence de paiement.')
                ->danger()
                ->send();

            return;
        }

        try {
            $paymentService = app(\App\Services\Payment\PaymentService::class);

            $result = $paymentService->createPayment(auth()->user(), [
                'amount' => (float) $price,
                'type' => \App\Enums\PaymentType::SUBSCRIPTION->value,
                'agency_id' => $agency->id,
                'plan_id' => $plan->id,
                'period' => $this->period,
                'description' => 'Abonnement '.$plan->name.' ('.$this->period.')',
            ]);

            session(['keyhome_pending_transaction' => $result['tx_ref']]);

            return redirect()->away($result['link']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Payment initiation error: '.$e->getMessage());

            \Filament\Notifications\Notification::make()
                ->title('Erreur de paiement')
                ->body('Impossible d\'initier le paiement. Veuillez réessayer.')
                ->danger()
                ->send();
        }
    }

    public function cancelSubscription()
    {
        if (!$this->subscription) {
            return;
        }

        $service = app(\App\Services\SubscriptionService::class);
        /** @var \App\Models\Agency $agency */
        $agency = auth()->user()->agency;
        $service->cancelActiveSubscriptions($agency, 'Annulation par l\'utilisateur');

        $this->subscription = null;

        \Filament\Notifications\Notification::make()
            ->title('Abonnement annulé')
            ->body('Votre abonnement a été annulé.')
            ->success()
            ->send();
    }
}

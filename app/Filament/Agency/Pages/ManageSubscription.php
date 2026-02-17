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

        // Verify payment status with FedaPay API before activating
        try {
            $fedaPayService = app(\App\Services\FedaPayService::class);
            $transaction = $fedaPayService->retrieveTransaction($transactionId);

            if ($transaction['status'] !== 'approved') {
                \Illuminate\Support\Facades\Log::warning('FedaPay verification failed for subscription payment', [
                    'transaction_id' => $transactionId,
                    'agency_id' => $agency->id,
                    'fedapay_status' => $transaction['status'],
                ]);

                return;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FedaPay API verification error: '.$e->getMessage());

            return;
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $payment->update(['status' => \App\Enums\PaymentStatus::SUCCESS]);

            $plan = \App\Models\SubscriptionPlan::find($payment->plan_id);

            if ($plan) {
                $subscriptionService = new \App\Services\SubscriptionService;
                $subscription = $subscriptionService->createSubscription($agency, $plan, $payment->period ?? 'monthly', $payment);
                $subscriptionService->activateSubscription($subscription);

                \Filament\Notifications\Notification::make()
                    ->title('Paiement réussi !')
                    ->body('Votre abonnement a été activé avec succès. Bienvenue !')
                    ->success()
                    ->send();
            }

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erreur activation manuelle: '.$e->getMessage());
        }
    }

    public function setPeriod(string $period)
    {
        $this->period = $period;
    }

    public function subscribe(string $planId)
    {
        $plan = \App\Models\SubscriptionPlan::findOrFail($planId);
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

        $fedaPay = new \App\Services\FedaPayService;

        // On génère l'URL de retour correcte pour Filament Multi-tenancy
        $callbackUrl = \Filament\Facades\Filament::getPanel('agency')->getUrl($agency).'/abonnement';

        $result = $fedaPay->createSubscriptionPayment(
            (int) $price,
            $agency,
            $plan->id,
            $this->period,
            $callbackUrl
        );

        if ($result['success']) {
            /** @var \App\Models\Agency $agency */
            $agency = auth()->user()->agency;

            // Créer le paiement en attente dans notre base pour le suivi
            \App\Models\Payment::create([
                'user_id' => auth()->id(),
                'agency_id' => $agency->id,
                'type' => \App\Enums\PaymentType::SUBSCRIPTION,
                'transaction_id' => $result['transaction_id'],
                'amount' => $price,
                'plan_id' => $plan->id,
                'period' => $this->period,
                'status' => \App\Enums\PaymentStatus::PENDING,
                'payment_method' => \App\Enums\PaymentMethod::FEDAPAY,
            ]);

            // Rediriger vers FedaPay
            return redirect()->away($result['url']);
        }

        \Filament\Notifications\Notification::make()
            ->title('Erreur FedaPay')
            ->body($result['message'])
            ->danger()
            ->send();
    }

    public function cancelSubscription()
    {
        if (!$this->subscription) {
            return;
        }

        $service = new \App\Services\SubscriptionService;
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

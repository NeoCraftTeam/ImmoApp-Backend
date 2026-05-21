<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\PaymentMethod;
use App\Services\Payment\PaymentMethodGateService;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 *
 * Admin-controlled per-method gating UI.
 *
 * Each toggle persists immediately to the `settings` table via
 * `PaymentMethodGateService` (cached 5 min) and is consumed by:
 *   - `GET /api/v1/payments/methods` — public catalogue
 *   - `FlutterwaveInitiateRequest::withValidator()` — rejects disabled methods
 *
 * No email OTP confirmation : a disabled method is the desired safe default
 * (rejecting payments) — the blast radius is bounded compared to pricing.
 */
class PaymentMethods extends Page
{
    protected static string|null|UnitEnum $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::CreditCard;

    protected static ?string $navigationLabel = 'Moyens de paiement';

    protected static ?string $title = 'Moyens de paiement';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.admin.pages.payment-methods';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        /** @var PaymentMethodGateService $gate */
        $gate = app(PaymentMethodGateService::class);

        $this->form->fill([
            'mobile_money' => $gate->isEnabled(PaymentMethod::MOBILE_MONEY),
            'orange_money' => $gate->isEnabled(PaymentMethod::ORANGE_MONEY),
            'card' => $gate->isEnabled(PaymentMethod::CARD),
            'flutterwave' => $gate->isEnabled(PaymentMethod::FLUTTERWAVE),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Paiement mobile (GeniusPay)')
                    ->description('Désactivez ces toggles pour bloquer immédiatement les paiements Mobile Money / Orange Money sur la plateforme. Les utilisateurs verront le moyen disparaître de la modale de paiement et toute tentative d\'initialisation sera rejetée par l\'API.')
                    ->icon(Heroicon::DevicePhoneMobile)
                    ->schema([
                        Toggle::make('mobile_money')
                            ->label('MTN Mobile Money')
                            ->helperText('Paiement via le réseau MTN MoMo (Cameroun, CEMAC).')
                            ->onColor('success')
                            ->live()
                            ->afterStateUpdated(fn (bool $state) => $this->persistToggle(PaymentMethod::MOBILE_MONEY, $state)),
                        Toggle::make('orange_money')
                            ->label('Orange Money')
                            ->helperText('Paiement via le réseau Orange Money (Cameroun, CEMAC, UEMOA).')
                            ->onColor('success')
                            ->live()
                            ->afterStateUpdated(fn (bool $state) => $this->persistToggle(PaymentMethod::ORANGE_MONEY, $state)),
                    ])
                    ->columns(2),

                Section::make('Carte bancaire (Stripe)')
                    ->description('Visa, Mastercard, Apple Pay, Google Pay — facturé en EUR via Stripe (taux figé 1 EUR = 655.957 XAF). Désactivez si vos clés Stripe ne sont pas encore configurées en production.')
                    ->icon(Heroicon::CreditCard)
                    ->schema([
                        Toggle::make('card')
                            ->label('Carte bancaire (Stripe)')
                            ->helperText('Facturation en EUR. Nécessite STRIPE_KEY, STRIPE_SECRET et STRIPE_WEBHOOK_SECRET dans .env.')
                            ->onColor('success')
                            ->live()
                            ->afterStateUpdated(fn (bool $state) => $this->persistToggle(PaymentMethod::CARD, $state)),
                    ]),

                Section::make('Avancé')
                    ->description('Le moyen « Autre · Mobile Money » est un libellé générique historique (compatibilité avec d\'anciens paiements). Il n\'apparaît plus dans la sélection utilisateur — laissez-le activé sauf maintenance.')
                    ->icon(Heroicon::WrenchScrewdriver)
                    ->collapsed()
                    ->schema([
                        Toggle::make('flutterwave')
                            ->label('Autre mobile (legacy)')
                            ->helperText('Compatibilité historique. Désactiver bloquerait les retries de paiements existants — ne touchez que pour une maintenance.')
                            ->onColor('warning')
                            ->live()
                            ->afterStateUpdated(fn (bool $state) => $this->persistToggle(PaymentMethod::FLUTTERWAVE, $state)),
                    ]),
            ]);
    }

    /**
     * Persist a single toggle change to the settings table and notify
     * the operator. Called inline by `afterStateUpdated` so toggling is
     * effectively instantaneous from the user's perspective.
     */
    private function persistToggle(PaymentMethod $method, bool $enabled): void
    {
        /** @var PaymentMethodGateService $gate */
        $gate = app(PaymentMethodGateService::class);

        $enabled ? $gate->enable($method) : $gate->disable($method);

        activity('payment_methods')
            ->causedBy(auth()->user())
            ->withProperties([
                'method' => $method->value,
                'enabled' => $enabled,
            ])
            ->event($enabled ? 'enabled' : 'disabled')
            ->log(sprintf(
                'Moyen de paiement « %s » %s',
                $method->label(),
                $enabled ? 'activé' : 'désactivé',
            ));

        Notification::make()
            ->title($method->label().' '.($enabled ? 'activé' : 'désactivé'))
            ->body($enabled
                ? 'Les nouveaux paiements via ce moyen sont autorisés.'
                : 'Les nouveaux paiements via ce moyen seront refusés. Les paiements en cours ne sont pas affectés.')
            ->color($enabled ? 'success' : 'warning')
            ->send();
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Mail\PricingVerificationMail;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

/**
 * @property-read \Filament\Schemas\Schema $form
 */
class ManageSettings extends Page
{
    protected static string|null|UnitEnum $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;

    protected static ?string $navigationLabel = 'Paramètres';

    protected static ?string $title = 'Paramètres de la plateforme';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.manage-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $awaitingSection = '';

    public string $verificationCode = '';

    public function mount(): void
    {
        $this->form->fill([
            'unlock_cost_points' => Setting::get('unlock_cost_points', 2),
            'welcome_bonus_points' => Setting::get('welcome_bonus_points', 5),
            'ad_lifetime_days' => Setting::get('ad_lifetime_days', 30),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Système de crédits')
                    ->description('Configuration du système de crédits utilisé pour débloquer les annonces')
                    ->icon(Heroicon::Star)
                    ->schema([
                        TextInput::make('unlock_cost_points')
                            ->label('Coût de déblocage (crédits)')
                            ->helperText('Nombre de crédits nécessaires pour débloquer une annonce.')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('crédits')
                            ->default(2),
                        TextInput::make('welcome_bonus_points')
                            ->label('Bonus de bienvenue (crédits)')
                            ->helperText('Nombre de crédits offerts automatiquement aux nouveaux utilisateurs à l\'inscription.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('crédits')
                            ->default(5),
                    ])
                    ->columns(2)
                    ->footerActions([
                        Action::make('saveCredits')
                            ->label('Modifier les crédits')
                            ->icon('heroicon-o-shield-check')
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalIcon('heroicon-o-shield-exclamation')
                            ->modalIconColor('warning')
                            ->modalHeading('Modification sensible')
                            ->modalDescription('Cette action modifie la configuration des crédits pour tous les utilisateurs. Un code de vérification sera envoyé à votre adresse email.')
                            ->modalSubmitActionLabel('Envoyer le code de vérification')
                            ->action(fn () => $this->sendVerificationCode('credits'))
                            ->visible(fn (): bool => $this->awaitingSection !== 'credits'),
                        Action::make('verifyCreditsCode')
                            ->label('Confirmer avec le code')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->form([
                                TextInput::make('code')
                                    ->label('Code de vérification')
                                    ->required()
                                    ->length(6)
                                    ->placeholder('000000')
                                    ->autofocus()
                                    ->extraInputAttributes([
                                        'class' => 'text-center text-2xl tracking-widest font-mono',
                                        'inputmode' => 'numeric',
                                    ]),
                            ])
                            ->modalIcon('heroicon-o-envelope')
                            ->modalIconColor('success')
                            ->modalHeading('Vérification par email')
                            ->modalDescription('Saisissez le code à 6 chiffres reçu par email pour confirmer la modification des crédits.')
                            ->modalSubmitActionLabel('Confirmer la modification')
                            ->action(function (array $data): void {
                                $this->verificationCode = (string) $data['code'];
                                $this->confirmWithCode('credits');
                            })
                            ->visible(fn (): bool => $this->awaitingSection === 'credits'),
                        Action::make('cancelCreditsVerification')
                            ->label('Annuler la vérification')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->action(fn () => $this->cancelVerification())
                            ->visible(fn (): bool => $this->awaitingSection === 'credits'),
                    ]),
                Section::make('Annonces')
                    ->description('Configuration de la durée de vie des annonces')
                    ->icon(Heroicon::Home)
                    ->schema([
                        TextInput::make('ad_lifetime_days')
                            ->label('Durée de vie d\'une annonce (jours)')
                            ->helperText('Nombre de jours avant qu\'une annonce n\'expire automatiquement après approbation.')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('jours')
                            ->default(30),
                    ])
                    ->footerActions([
                        Action::make('saveAds')
                            ->label('Modifier la durée')
                            ->icon('heroicon-o-shield-check')
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalIcon('heroicon-o-shield-exclamation')
                            ->modalIconColor('warning')
                            ->modalHeading('Modification sensible')
                            ->modalDescription('Cette action modifie la durée de vie des annonces pour l\'ensemble de la plateforme. Un code de vérification sera envoyé à votre adresse email.')
                            ->modalSubmitActionLabel('Envoyer le code de vérification')
                            ->action(fn () => $this->sendVerificationCode('ads'))
                            ->visible(fn (): bool => $this->awaitingSection !== 'ads'),
                        Action::make('verifyAdsCode')
                            ->label('Confirmer avec le code')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->form([
                                TextInput::make('code')
                                    ->label('Code de vérification')
                                    ->required()
                                    ->length(6)
                                    ->placeholder('000000')
                                    ->autofocus()
                                    ->extraInputAttributes([
                                        'class' => 'text-center text-2xl tracking-widest font-mono',
                                        'inputmode' => 'numeric',
                                    ]),
                            ])
                            ->modalIcon('heroicon-o-envelope')
                            ->modalIconColor('success')
                            ->modalHeading('Vérification par email')
                            ->modalDescription('Saisissez le code à 6 chiffres reçu par email pour confirmer la modification de la durée des annonces.')
                            ->modalSubmitActionLabel('Confirmer la modification')
                            ->action(function (array $data): void {
                                $this->verificationCode = (string) $data['code'];
                                $this->confirmWithCode('ads');
                            })
                            ->visible(fn (): bool => $this->awaitingSection === 'ads'),
                        Action::make('cancelAdsVerification')
                            ->label('Annuler la vérification')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->action(fn () => $this->cancelVerification())
                            ->visible(fn (): bool => $this->awaitingSection === 'ads'),
                    ]),
            ]);
    }

    /**
     * Send a verification code by email for the given section.
     */
    public function sendVerificationCode(string $section): void
    {
        $this->form->getState();

        $user = auth()->user();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("settings_verification_{$section}_{$user->id}", $code, now()->addMinutes(10));

        Mail::to($user->email)->send(new PricingVerificationMail($user, $code));

        $this->awaitingSection = $section;

        Notification::make()
            ->title('Code envoyé')
            ->body("Un code de vérification a été envoyé à {$user->email}")
            ->info()
            ->send();
    }

    /**
     * Verify the code and apply the save for the given section.
     */
    public function confirmWithCode(string $section): void
    {
        $user = auth()->user();
        $expectedCode = Cache::get("settings_verification_{$section}_{$user->id}");

        if (!$expectedCode || $this->verificationCode !== $expectedCode) {
            Notification::make()
                ->title('Code invalide')
                ->body('Le code saisi est incorrect ou a expiré.')
                ->danger()
                ->send();

            return;
        }

        Cache::forget("settings_verification_{$section}_{$user->id}");

        match ($section) {
            'credits' => $this->saveCreditsSettings(),
            'ads' => $this->saveAdsSettings(),
            default => null,
        };

        $this->awaitingSection = '';
        $this->verificationCode = '';
    }

    /**
     * Save credits settings (unlock_cost_points + welcome_bonus_points).
     */
    public function saveCreditsSettings(): void
    {
        $data = $this->form->getState();
        $oldUnlockCost = Setting::get('unlock_cost_points', 2);
        $oldWelcomeBonus = Setting::get('welcome_bonus_points', 5);

        Setting::set(
            'unlock_cost_points',
            $data['unlock_cost_points'],
            'Coût de déblocage en crédits',
            'credits'
        );

        Setting::set(
            'welcome_bonus_points',
            $data['welcome_bonus_points'],
            'Bonus de bienvenue (crédits)',
            'credits'
        );

        activity('settings')
            ->causedBy(auth()->user())
            ->performedOn(Setting::find('unlock_cost_points'))
            ->withProperties([
                'old' => [
                    'unlock_cost_points' => $oldUnlockCost,
                    'welcome_bonus_points' => $oldWelcomeBonus,
                ],
                'attributes' => [
                    'unlock_cost_points' => $data['unlock_cost_points'],
                    'welcome_bonus_points' => $data['welcome_bonus_points'],
                ],
            ])
            ->event('updated')
            ->log('Modification des paramètres de crédits');

        Notification::make()
            ->title('Crédits mis à jour')
            ->body("Coût de déblocage : {$data['unlock_cost_points']} crédits. Bonus de bienvenue : {$data['welcome_bonus_points']} crédits.")
            ->success()
            ->send();
    }

    /**
     * Save ad lifetime setting.
     */
    public function saveAdsSettings(): void
    {
        $data = $this->form->getState();
        $oldValue = Setting::get('ad_lifetime_days', 30);

        Setting::set(
            'ad_lifetime_days',
            $data['ad_lifetime_days'],
            'Durée de vie d\'une annonce (jours)',
            'ads'
        );

        activity('settings')
            ->causedBy(auth()->user())
            ->performedOn(Setting::find('ad_lifetime_days'))
            ->withProperties([
                'old' => ['ad_lifetime_days' => $oldValue],
                'attributes' => ['ad_lifetime_days' => $data['ad_lifetime_days']],
            ])
            ->event('updated')
            ->log('Modification de la durée de vie des annonces');

        Notification::make()
            ->title('Annonces mis à jour')
            ->body("Durée de vie des annonces : {$data['ad_lifetime_days']} jours.")
            ->success()
            ->send();
    }

    public function cancelVerification(): void
    {
        $section = $this->awaitingSection;
        $this->awaitingSection = '';
        $this->verificationCode = '';

        if ($section) {
            Cache::forget("settings_verification_{$section}_".auth()->id());
        }
    }
}

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

class ManageSettings extends Page
{
    protected static string|null|UnitEnum $navigationGroup = 'Paiements';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;

    protected static ?string $navigationLabel = 'Tarification';

    protected static ?string $title = 'Tarification & Prix';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.admin.pages.manage-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public bool $awaitingCode = false;

    public string $verificationCode = '';

    public function mount(): void
    {
        $this->form->fill([
            'unlock_price' => Setting::get('unlock_price', 500),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Paiements')
                    ->description('Configuration des tarifs de l\'application')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        TextInput::make('unlock_price')
                            ->label('Prix de déblocage d\'une annonce (FCFA)')
                            ->helperText('Montant facturé pour débloquer une annonce et voir toutes les images et le contact du propriétaire.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('FCFA')
                            ->default(500),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Modifier la tarification')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-shield-exclamation')
                ->modalIconColor('warning')
                ->modalHeading('Modification sensible')
                ->modalDescription('Cette action modifie le prix de déblocage pour tous les utilisateurs. Un code de vérification sera envoyé à votre adresse email.')
                ->modalSubmitActionLabel('Envoyer le code de vérification')
                ->action(fn () => $this->sendVerificationCode())
                ->visible(fn (): bool => !$this->awaitingCode),

            Action::make('verifyCode')
                ->label('Confirmer avec le code')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalIcon('heroicon-o-envelope')
                ->modalIconColor('success')
                ->modalHeading('Vérification par email')
                ->modalDescription('Saisissez le code à 6 chiffres reçu par email pour confirmer la modification.')
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
                ->modalSubmitActionLabel('Confirmer la modification')
                ->action(function (array $data): void {
                    $this->verificationCode = (string) $data['code'];
                    $this->confirmWithCode();
                })
                ->visible(fn (): bool => $this->awaitingCode),

            Action::make('cancelVerification')
                ->label('Annuler')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action(fn () => $this->cancelVerification())
                ->visible(fn (): bool => $this->awaitingCode),
        ];
    }

    /**
     * Step 1: Validate form, generate and send verification code by email.
     */
    public function sendVerificationCode(): void
    {
        $this->form->getState();

        $user = auth()->user();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("pricing_verification_{$user->id}", $code, now()->addMinutes(10));

        Mail::to($user->email)->send(new PricingVerificationMail($user, $code));

        $this->awaitingCode = true;

        Notification::make()
            ->title('Code envoyé')
            ->body("Un code de vérification a été envoyé à {$user->email}")
            ->info()
            ->send();
    }

    /**
     * Step 2: Verify code and apply changes.
     */
    public function confirmWithCode(): void
    {
        $user = auth()->user();
        $expectedCode = Cache::get("pricing_verification_{$user->id}");

        if (!$expectedCode || $this->verificationCode !== $expectedCode) {
            Notification::make()
                ->title('Code invalide')
                ->body('Le code saisi est incorrect ou a expiré.')
                ->danger()
                ->send();

            return;
        }

        Cache::forget("pricing_verification_{$user->id}");

        $data = $this->form->getState();

        Setting::set(
            'unlock_price',
            $data['unlock_price'],
            'Prix de déblocage d\'une annonce (FCFA)',
            'payments'
        );

        $this->awaitingCode = false;
        $this->verificationCode = '';

        Notification::make()
            ->title('Tarification mise à jour')
            ->body("Le prix de déblocage est maintenant de {$data['unlock_price']} FCFA.")
            ->success()
            ->send();
    }

    public function cancelVerification(): void
    {
        $this->awaitingCode = false;
        $this->verificationCode = '';

        Cache::forget('pricing_verification_'.auth()->id());
    }
}

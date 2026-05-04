<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Filament\Forms\Components\NativePhoneInput;
use App\Mail\GdprDataExportMail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class EditProfile extends BaseEditProfile
{
    #[\Override]
    public static function isSimple(): bool
    {
        return false;
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour au tableau de bord')
                ->url(filament()->getCurrentPanel()->getUrl())
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->labeledFrom('md'),
        ];
    }

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Photo de profil')
                    ->icon('heroicon-o-camera')
                    ->description('Votre avatar visible par les autres utilisateurs')
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('')
                            ->disk(config('filesystems.app_media_disk'))
                            ->visibility('public')
                            ->directory('avatars')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->fetchFileInformation(false)
                            ->extraAttributes([
                                'data-native-input' => 'image',
                                'data-native-type' => 'avatar',
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Informations personnelles')
                    ->icon('heroicon-o-user')
                    ->description('Vos informations de base')
                    ->schema([
                        TextInput::make('firstname')
                            ->label('Prénom')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-user'),
                        TextInput::make('lastname')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-user'),
                        $this->getEmailFormComponent(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Contact')
                    ->icon('heroicon-o-phone')
                    ->description('Vos coordonnées téléphoniques')
                    ->schema([
                        NativePhoneInput::make('phone_number')
                            ->label('Numéro de téléphone')
                            ->required()
                            ->placeholder('+237 6XX XXX XXX')
                            ->helperText('Numéro que les clients utiliseront pour vous contacter'),
                        Checkbox::make('phone_is_whatsapp')
                            ->label('Ce numéro est disponible sur WhatsApp')
                            ->helperText('Permet aux clients de vous contacter via WhatsApp'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Sécurité')
                    ->icon('heroicon-o-lock-closed')
                    ->description('Modifiez votre mot de passe')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('Passkeys')
                    ->icon('heroicon-o-finger-print')
                    ->description('Connectez-vous sans mot de passe grâce aux passkeys (empreinte, Face ID, clé USB)')
                    ->schema([
                        ViewField::make('passkeys_view')
                            ->label('')
                            ->view('filament.admin.components.passkey-manager')
                            ->viewData(['passkeys' => $this->getPasskeys()])
                            ->dehydrated(false),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Section::make('Données & confidentialité')
                    ->icon('heroicon-o-shield-check')
                    ->description('Accédez à vos données personnelles conformément au RGPD')
                    ->footerActions([
                        Action::make('exportGdpr')
                            ->label('Exporter mes données par email')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalHeading('Exporter vos données personnelles')
                            ->modalDescription('Un email contenant toutes vos données personnelles (format JSON) sera envoyé à votre adresse email. Cette opération peut prendre quelques minutes.')
                            ->modalSubmitActionLabel('Envoyer l\'export')
                            ->action(fn () => $this->sendGdprExport()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return list<array{id: string, alias: string|null, created_at: string, last_used: string|null}>
     */
    public function getPasskeys(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->webAuthnCredentials()
            ->whereEnabled()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebAuthnCredential $cred): array => [
                'id' => $cred->getKey(),
                'alias' => $cred->alias ?? null,
                'created_at' => $cred->created_at->format('d/m/Y à H:i'),
                'last_used' => $cred->updated_at->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    public function sendGdprExport(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            Mail::to($user->email)->send(new GdprDataExportMail($user));

            Notification::make()
                ->title('Export envoyé')
                ->body('Vos données ont été envoyées à '.$user->email.'. Vérifiez votre boîte mail dans quelques minutes.')
                ->success()
                ->duration(8000)
                ->send();
        } catch (\Throwable) {
            Notification::make()
                ->title('Erreur lors de l\'export')
                ->body('Impossible d\'envoyer l\'email. Veuillez réessayer.')
                ->danger()
                ->send();
        }
    }
}

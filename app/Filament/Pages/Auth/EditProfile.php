<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Filament\Forms\Components\NativePhoneInput;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditProfile extends BaseEditProfile
{
    #[\Override]
    public static function isSimple(): bool
    {
        return false;
    }

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
                            ->disk('public')
                            ->directory('avatars')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
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
            ]);
    }
}

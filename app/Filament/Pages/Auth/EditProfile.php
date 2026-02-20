<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->label('Photo de profil')
                    ->disk('public')
                    ->directory('avatars')
                    ->avatar()
                    ->image()
                    ->imageEditor()
                    ->circleCropper()
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                TextInput::make('firstname')
                    ->label('Prénom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('lastname')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                $this->getEmailFormComponent(),
                Section::make('Téléphone')
                    ->schema([
                        TextInput::make('phone_number')
                            ->label('Numéro de téléphone')
                            ->tel()
                            ->required()
                            ->placeholder('+237 6XX XXX XXX')
                            ->helperText('Numéro que les clients utiliseront pour vous contacter'),
                        Checkbox::make('phone_is_whatsapp')
                            ->label('Ce numéro est disponible sur WhatsApp')
                            ->helperText('Permet aux clients de vous contacter via WhatsApp'),
                    ])
                    ->columns(1),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}

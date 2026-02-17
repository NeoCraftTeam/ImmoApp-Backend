<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\FileUpload::make('avatar')
                    ->label('Photo de profil')
                    ->directory('avatars')
                    ->avatar()
                    ->image()
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                \Filament\Forms\Components\TextInput::make('firstname')
                    ->label('Prénom')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\TextInput::make('lastname')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}

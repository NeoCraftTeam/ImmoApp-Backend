<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AgencyService;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CustomRegister extends BaseRegister
{
    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFirstnameFormComponent(),
                $this->getLastnameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getAgencyNameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getFirstnameFormComponent(): Component
    {
        return TextInput::make('firstname')
            ->label('Prénom')
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getLastnameFormComponent(): Component
    {
        return TextInput::make('lastname')
            ->label('Nom')
            ->required()
            ->maxLength(255);
    }

    protected function getAgencyNameFormComponent(): Component
    {
        $panelId = \Filament\Facades\Filament::getCurrentPanel()->getId();

        return TextInput::make('agency_name')
            ->label('Nom de votre agence')
            ->required($panelId === 'agency')
            ->visible($panelId === 'agency')
            ->maxLength(255);
    }

    #[\Override]
    protected function handleRegistration(array $data): Model
    {
        $panelId = \Filament\Facades\Filament::getCurrentPanel()->getId();

        // 1. On crée d'abord l'utilisateur de base
        $user = User::create([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::CUSTOMER, // Rôle temporaire avant promotion
            'is_active' => true,
        ]);

        // 2. On utilise le service pour lui créer son Agence/Portefeuille et le promouvoir Agent
        $agencyService = app(AgencyService::class);

        if ($panelId === 'agency') {
            $agencyName = $data['agency_name'] ?? 'Agence de '.$user->lastname;
            $agencyService->promoteToAgency($user, $agencyName);
        } else {
            $agencyService->promoteToBailleur($user);
        }

        return $user;
    }
}

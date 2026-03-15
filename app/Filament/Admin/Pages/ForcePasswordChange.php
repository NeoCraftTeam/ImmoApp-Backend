<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChange extends Page
{
    protected static ?string $title = 'Changement de mot de passe obligatoire';

    protected static ?string $slug = 'force-password-change';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.pages.force-password-change';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasMustChangePassword()) {
            $this->redirect(filament()->getCurrentPanel()->getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Sécurité')
                    ->description('Pour des raisons de sécurité, vous devez définir un nouveau mot de passe avant de continuer. Vous devrez ensuite configurer l\'authentification à deux facteurs (2FA).')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Mot de passe actuel')
                            ->password()
                            ->required()
                            ->revealable()
                            ->currentPassword(),
                        TextInput::make('password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->required()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->same('password_confirmation'),
                        TextInput::make('password_confirmation')
                            ->label('Confirmer le nouveau mot de passe')
                            ->password()
                            ->required()
                            ->revealable()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->footerActions([
                        \Filament\Actions\Action::make('submit')
                            ->label('Changer le mot de passe')
                            ->action('submit'),
                    ]),
            ]);
    }

    public function submit(): void
    {
        $this->form->validate();
        $data = $this->form->getState();

        auth()->user()->update([
            'password' => $data['password'],
            'must_change_password_at' => null,
        ]);

        Notification::make()
            ->title('Mot de passe mis à jour')
            ->body('Votre mot de passe a été modifié avec succès. Configurez maintenant l\'authentification à deux facteurs.')
            ->success()
            ->send();

        $this->redirect(filament()->getCurrentPanel()->getUrl());
    }

    protected function getFormActions(): array
    {
        return [];
    }
}

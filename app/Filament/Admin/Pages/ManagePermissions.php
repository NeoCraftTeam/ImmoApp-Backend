<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ManagePermissions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|UnitEnum $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $title = 'Gestion des permissions';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.admin.pages.manage-permissions';

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('firstname')
                    ->label('Prénom')
                    ->searchable(),
                TextColumn::make('lastname')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::ADMIN => 'danger',
                        UserRole::AGENT => 'warning',
                        UserRole::CUSTOMER => 'info',
                    }),
                TextColumn::make('is_active')
                    ->label('Actif')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Oui' : 'Non'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(UserRole::class),
            ])
            ->actions([
                Action::make('changeRole')
                    ->label('Changer le rôle')
                    ->icon(Heroicon::PencilSquare)
                    ->form([
                        Select::make('role')
                            ->label('Nouveau rôle')
                            ->options(UserRole::class)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['role' => $data['role']]);
                        Notification::make()
                            ->title('Rôle mis à jour')
                            ->body("Le rôle de {$record->firstname} {$record->lastname} a été changé en {$data['role']}.")
                            ->success()
                            ->send();
                    }),
                Action::make('toggleActive')
                    ->label(fn (User $record): string => $record->is_active ? 'Désactiver' : 'Activer')
                    ->icon(fn (User $record): string => $record->is_active ? Heroicon::XCircle->value : Heroicon::CheckCircle->value)
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['is_active' => !$record->is_active]);
                        $action = $record->is_active ? 'activé' : 'désactivé';
                        Notification::make()
                            ->title("Utilisateur {$action}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('activateAll')
                    ->label('Activer la sélection')
                    ->icon(Heroicon::CheckCircle)
                    ->action(function (EloquentCollection $records): void {
                        $records->each(function (Model $model): void {
                            if (!$model instanceof User) {
                                return;
                            }

                            $model->update(['is_active' => true]);
                        });
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}

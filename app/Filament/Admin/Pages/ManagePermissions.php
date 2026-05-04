<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\AdminPermission;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ManagePermissions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|UnitEnum $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $title = 'Centre de permissions';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.admin.pages.manage-permissions';

    public function getSubheading(): ?string
    {
        return "Pilotez l'accès de chaque administrateur. Les super-administrateurs disposent d'un accès total ; "
            .'les autres bénéficient uniquement des permissions explicitement accordées.';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSuperAdmin() || $user->hasAdminPermission(AdminPermission::PermissionsManage));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('role', UserRole::ADMIN->value)
                    ->orderByDesc('is_super_admin')
                    ->orderBy('firstname')
            )
            ->heading('Administrateurs')
            ->description("Toutes les personnes disposant du rôle Admin et l'étendue de leurs droits.")
            ->striped()
            ->deferLoading()
            ->columns([
                TextColumn::make('full_name')
                    ->label('Administrateur')
                    ->state(fn (User $record): string => trim($record->firstname.' '.$record->lastname) ?: $record->email)
                    ->description(fn (User $record): string => (string) $record->email)
                    ->searchable(['firstname', 'lastname', 'email'])
                    ->wrap(),
                IconColumn::make('is_super_admin')
                    ->label('Super-admin')
                    ->boolean()
                    ->trueIcon(Heroicon::ShieldCheck)
                    ->falseIcon(Heroicon::ShieldExclamation)
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('admin_permissions_count')
                    ->label('Permissions')
                    ->state(function (User $record): string {
                        if ($record->isSuperAdmin()) {
                            return 'Accès total';
                        }
                        $count = is_array($record->admin_permissions)
                            ? count($record->admin_permissions)
                            : 0;

                        return $count === 0
                            ? 'Aucune'
                            : sprintf('%d / %d', $count, count(AdminPermission::cases()));
                    })
                    ->badge()
                    ->color(function (User $record): string {
                        if ($record->isSuperAdmin()) {
                            return 'success';
                        }
                        $count = is_array($record->admin_permissions)
                            ? count($record->admin_permissions)
                            : 0;

                        return match (true) {
                            $count === 0 => 'gray',
                            $count <= 5 => 'info',
                            $count <= 12 => 'warning',
                            default => 'success',
                        };
                    }),
                IconColumn::make('is_active')
                    ->label('Compte actif')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::NoSymbol)
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('last_login_at')
                    ->label('Dernière connexion')
                    ->dateTime('d/m/Y à H:i')
                    ->placeholder('Jamais')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('super_admin')
                    ->label('Super-administrateurs uniquement')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('is_super_admin', true)),
                Filter::make('inactive')
                    ->label('Comptes désactivés')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('is_active', false)),
            ])
            ->recordActions([
                Action::make('togglesuperAdmin')
                    ->label(fn (User $record): string => $record->isSuperAdmin() ? 'Retirer super-admin' : 'Promouvoir super-admin')
                    ->icon(fn (User $record): Heroicon => $record->isSuperAdmin() ? Heroicon::ShieldExclamation : Heroicon::ShieldCheck)
                    ->color(fn (User $record): string => $record->isSuperAdmin() ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->isSuperAdmin()
                        ? 'Retirer le statut super-administrateur'
                        : 'Promouvoir au rang de super-administrateur')
                    ->modalDescription(fn (User $record): string => $record->isSuperAdmin()
                        ? "L'utilisateur conservera ses permissions ciblées mais perdra l'accès total."
                        : 'Cet utilisateur aura accès à toutes les ressources sans restriction. À utiliser avec parcimonie.')
                    ->action(function (User $record): void {
                        $record->update(['is_super_admin' => !$record->isSuperAdmin()]);
                        Notification::make()
                            ->title($record->isSuperAdmin() ? 'Super-admin promu' : 'Statut super-admin retiré')
                            ->body($record->firstname.' '.$record->lastname)
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isSuperAdmin()),

                Action::make('editPermissions')
                    ->label('Modifier les permissions')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (User $record): string => 'Permissions de '.$record->firstname.' '.$record->lastname)
                    ->modalDescription('Sélectionnez les fonctionnalités auxquelles cet administrateur a accès. Les permissions sont ignorées si le statut super-admin est activé.')
                    ->fillForm(fn (User $record): array => [
                        'is_super_admin' => $record->isSuperAdmin(),
                        'admin_permissions' => (array) ($record->admin_permissions ?? []),
                    ])
                    ->schema([
                        Toggle::make('is_super_admin')
                            ->label('Accorder le statut super-administrateur')
                            ->helperText('Donne accès à toutes les ressources sans restriction.')
                            ->onColor('success')
                            ->live(),
                        CheckboxList::make('admin_permissions')
                            ->label('Permissions ciblées')
                            ->options(self::groupedPermissionOptions())
                            ->columns(1)
                            ->bulkToggleable()
                            ->searchable()
                            ->disabled(fn (Get $get): bool => (bool) $get('is_super_admin'))
                            ->helperText('Cliquez sur les puces ci-dessous pour appliquer un preset.'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'is_super_admin' => (bool) ($data['is_super_admin'] ?? false),
                            'admin_permissions' => (bool) ($data['is_super_admin'] ?? false)
                                ? null
                                : array_values(array_filter((array) ($data['admin_permissions'] ?? []))),
                        ]);

                        Notification::make()
                            ->title('Permissions mises à jour')
                            ->body($record->firstname.' '.$record->lastname)
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    Action::make('presetReadOnly')
                        ->label('Preset : Lecture seule')
                        ->icon(Heroicon::Eye)
                        ->color('gray')
                        ->action(fn (User $record) => $this->applyPreset($record, AdminPermission::readOnlyDefaults())),
                    Action::make('presetModerator')
                        ->label('Preset : Modérateur')
                        ->icon(Heroicon::ShieldCheck)
                        ->color('warning')
                        ->action(fn (User $record) => $this->applyPreset($record, AdminPermission::moderatorPreset())),
                    Action::make('presetNewsletter')
                        ->label('Preset : Newsletter')
                        ->icon(Heroicon::Megaphone)
                        ->color('info')
                        ->action(fn (User $record) => $this->applyPreset($record, AdminPermission::newsletterEditorPreset())),
                    Action::make('clearPermissions')
                        ->label('Tout révoquer')
                        ->icon(Heroicon::XMark)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Cet administrateur perdra immédiatement tous ses droits.')
                        ->action(function (User $record): void {
                            $record->update([
                                'is_super_admin' => false,
                                'admin_permissions' => [],
                            ]);
                            Notification::make()
                                ->title('Permissions révoquées')
                                ->body($record->firstname.' '.$record->lastname)
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Presets')
                    ->icon(Heroicon::Sparkles)
                    ->color('gray')
                    ->button(),

                Action::make('toggleActive')
                    ->label(fn (User $record): string => $record->is_active ? 'Désactiver le compte' : 'Réactiver le compte')
                    ->icon(fn (User $record): Heroicon => $record->is_active ? Heroicon::NoSymbol : Heroicon::CheckCircle)
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->is_active
                        ? 'Désactiver l\'accès admin'
                        : 'Réactiver le compte admin')
                    ->action(function (User $record): void {
                        $record->update(['is_active' => !$record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Compte réactivé' : 'Compte désactivé')
                            ->body($record->firstname.' '.$record->lastname)
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('promoteToAdmin')
                    ->label('Promouvoir un utilisateur')
                    ->icon(Heroicon::UserPlus)
                    ->color('primary')
                    ->modalHeading('Promouvoir un utilisateur en administrateur')
                    ->modalDescription('Sélectionnez un compte existant à promouvoir. Le rôle devient « admin » avec un accès en lecture seule par défaut.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Utilisateur')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Rechercher par nom ou email')
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->where('role', '!=', UserRole::ADMIN->value)
                                ->where(function (Builder $query) use ($search): void {
                                    $query->where('firstname', 'ilike', "%{$search}%")
                                        ->orWhere('lastname', 'ilike', "%{$search}%")
                                        ->orWhere('email', 'ilike', "%{$search}%");
                                })
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (User $u): array => [$u->id => $u->firstname.' '.$u->lastname.' — '.$u->email])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->email),
                        Toggle::make('grant_super_admin')
                            ->label('Accorder le statut super-administrateur')
                            ->helperText('Recommandé uniquement pour les fondateurs / DSI.')
                            ->onColor('warning'),
                    ])
                    ->action(function (array $data): void {
                        $user = User::find($data['user_id'] ?? null);
                        if (!$user instanceof User) {
                            Notification::make()->title('Utilisateur introuvable')->danger()->send();

                            return;
                        }
                        $user->update([
                            'role' => UserRole::ADMIN->value,
                            'is_super_admin' => (bool) ($data['grant_super_admin'] ?? false),
                            'admin_permissions' => (bool) ($data['grant_super_admin'] ?? false)
                                ? null
                                : array_map(fn (AdminPermission $p): string => $p->value, AdminPermission::readOnlyDefaults()),
                        ]);
                        Notification::make()
                            ->title('Promu administrateur')
                            ->body($user->firstname.' '.$user->lastname.' a désormais accès au panel admin.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isSuperAdmin()),
            ]);
    }

    private function applyPreset(User $record, array $permissions): void
    {
        $values = array_map(fn (AdminPermission $p): string => $p->value, $permissions);
        $record->update([
            'is_super_admin' => false,
            'admin_permissions' => $values,
        ]);
        Notification::make()
            ->title('Preset appliqué')
            ->body($record->firstname.' '.$record->lastname.' — '.count($values).' permission(s) accordée(s).')
            ->success()
            ->send();
    }

    /**
     * Build the grouped checkbox-list options for permissions.
     *
     * @return array<string, string>
     */
    protected static function groupedPermissionOptions(): array
    {
        $options = [];
        foreach (AdminPermission::grouped() as $groupLabel => $cases) {
            foreach ($cases as $case) {
                $options[$case->value] = sprintf('[%s] %s', $groupLabel, $case->getLabel());
            }
        }

        return $options;
    }
}

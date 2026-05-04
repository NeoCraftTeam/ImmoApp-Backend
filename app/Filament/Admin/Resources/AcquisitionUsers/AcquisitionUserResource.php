<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AcquisitionUsers;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\AcquisitionUsers\Pages\ManageAcquisitionUsers;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AcquisitionUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'acquisition-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Analytique';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Inscriptions par canal';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs & acquisition';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'email';

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::AcquisitionView) ?? false;
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['city']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('firstname')
                    ->label('Prénom')
                    ->searchable(),
                TextColumn::make('lastname')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->sortable(),
                TextColumn::make('acquisition_source')
                    ->label('Canal')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('utm_source')
                    ->label('utm_source')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_medium')
                    ->label('utm_medium')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_campaign')
                    ->label('utm_campaign')
                    ->limit(28)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_content')
                    ->label('utm_content')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('utm_term')
                    ->label('utm_term')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referrer_domain')
                    ->label('Référent')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('acquisition_source')
                    ->label('Canal')
                    ->options([
                        'direct' => 'Direct',
                        'organic' => 'Organique',
                        'social' => 'Social',
                        'referral' => 'Référence',
                        'paid' => 'Payant',
                        'email' => 'Email',
                    ]),
                Filter::make('created_at')
                    ->label('Période d’inscription')
                    ->form([
                        DatePicker::make('from')->label('Du')->native(false),
                        DatePicker::make('until')->label('Au')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Depuis le '.Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Jusqu\'au '.Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Fiche utilisateur')
                    ->url(fn (User $record): string => UserResource::getUrl('view', ['record' => $record])),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageAcquisitionUsers::route('/'),
        ];
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function canEdit($record): bool
    {
        return false;
    }

    #[\Override]
    public static function canDelete($record): bool
    {
        return false;
    }
}

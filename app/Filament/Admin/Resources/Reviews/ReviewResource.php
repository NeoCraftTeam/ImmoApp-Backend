<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Reviews;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::ReviewsManage) ?? false;
    }

    protected static string|null|\UnitEnum $navigationGroup = 'Membres';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static ?string $recordTitleAttribute = 'rating';

    protected static ?string $navigationLabel = 'Avis des clients';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Avis clients';

    protected static ?string $pluralModelLabel = 'Avis des clients';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ad', 'user.agency']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Détails de l\'avis')
                    ->icon(Heroicon::Star)
                    ->columns(2)
                    ->schema([
                        TextInput::make('rating')
                            ->label('Note')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->step(1)
                            ->helperText('Note de 1 à 5 étoiles'),
                        Select::make('ad_id')
                            ->label('Annonce')
                            ->relationship('ad', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'firstname')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->fullname)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('comment')
                            ->label('Commentaire')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // ── Évaluation ──────────────────────────────────────────────────
                Section::make('Évaluation')
                    ->icon(Heroicon::Star)
                    ->iconColor('warning')
                    ->description('Note et commentaire laissés par l\'utilisateur sur ce logement')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rating')
                            ->label('Note globale')
                            ->badge()
                            ->size('lg')
                            ->color(fn (int $state): string => match (true) {
                                $state >= 4 => 'success',
                                $state >= 3 => 'warning',
                                default => 'danger',
                            })
                            ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state).'  '.$state.' / 5'
                            ),
                        TextEntry::make('user.fullname')
                            ->label('Auteur de l\'avis')
                            ->icon(Heroicon::UserCircle)
                            ->iconColor('primary')
                            ->weight('semibold'),
                        TextEntry::make('ad.title')
                            ->label('Annonce concernée')
                            ->icon(Heroicon::Megaphone)
                            ->iconColor('info')
                            ->limit(80)
                            ->columnSpanFull(),
                        TextEntry::make('comment')
                            ->label('Commentaire')
                            ->placeholder('Aucun commentaire rédigé')
                            ->columnSpanFull()
                            ->prose(),
                    ]),

                // ── Horodatage ──────────────────────────────────────────────────
                Section::make('Horodatage')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Publié le')
                            ->icon(Heroicon::CalendarDays)
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->icon(Heroicon::PencilSquare)
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label('Supprimé le')
                            ->icon(Heroicon::Trash)
                            ->iconColor('danger')
                            ->dateTime('d/m/Y à H:i')
                            ->visible(fn (Review $record): bool => $record->trashed()),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Avis des clients')
            ->description('Gestion des avis et notes des utilisateurs')
            ->striped()
            ->recordTitleAttribute('user_id')
            ->columns([
                TextColumn::make('rating')
                    ->label('Note')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('user.fullname')
                    ->label('Utilisateur')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalIcon('heroicon-o-star')
                    ->modalIconColor('warning')
                    ->modalHeading(fn (Review $record): string => str_repeat('★', (int) $record->rating).str_repeat('☆', 5 - (int) $record->rating)
                        .'  Avis de '.($record->user->fullname ?? 'Utilisateur')
                    )
                    ->modalWidth('2xl'),
                DeleteAction::make()
                    ->successNotificationTitle('Avis supprimé'),
                ForceDeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->successNotificationTitle('Avis supprimé définitivement'),
                RestoreAction::make()
                    ->successNotificationTitle('Avis restauré'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }

    #[\Override]
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

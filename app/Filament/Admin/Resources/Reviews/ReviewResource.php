<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Reviews;

use App\Filament\Admin\Resources\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

    protected static string|null|\UnitEnum $navigationGroup = 'Utilisateurs';

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
            ->components([
                TextEntry::make('rating')
                    ->label('Note')
                    ->numeric(),
                TextEntry::make('comment')
                    ->label('Commentaire')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('ad.title')
                    ->label('Annonce'),
                TextEntry::make('user.fullname')
                    ->label('Utilisateur'),
                TextEntry::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y à H:i')
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y à H:i')
                    ->visible(fn (Review $record): bool => $record->trashed()),
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
                ViewAction::make(),
                EditAction::make()
                    ->successNotificationTitle('Avis mis à jour'),
                DeleteAction::make()
                    ->successNotificationTitle('Avis supprimé'),
                ForceDeleteAction::make()
                    ->successNotificationTitle('Avis supprimé définitivement'),
                RestoreAction::make()
                    ->successNotificationTitle('Avis restauré'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre d\'avis';
    }
}

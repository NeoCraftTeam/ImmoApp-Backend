<?php

namespace App\Filament\Admin\Resources\AdImages;

use App\Filament\Admin\Resources\AdImages\Pages\ManageAdImages;
use App\Models\Ad;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AdImageResource extends Resource
{
    // Utiliser le modèle Ad au lieu de AdImage pour regrouper par annonce
    protected static ?string $model = Ad::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Images des annonces';

    protected static ?string $modelLabel = 'Annonce avec images';

    protected static ?string $pluralModelLabel = 'Annonces avec images';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations de l\'annonce')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Titre'),
                        TextEntry::make('images_count')
                            ->label('Nombre d\'images')
                            ->state(fn($record) => $record->images()->count()),
                    ])
                    ->columns(2),

                Section::make('Images')
                    ->schema([
                        ImageEntry::make('images.image_path')
                            ->label('')
                            ->size(200)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Annonce')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),

                // Images empilées
                ImageColumn::make('images.image_path')
                    ->label('Images')
                    ->circular()
                    ->stacked()
                    ->limit(5)
                    ->limitedRemainingText()
                    ->ring(2),

                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->isoDateTime('LLLL', 'Europe/Paris')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdImages::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->has('images') // Afficher uniquement les annonces qui ont des images
            ->withCount('images');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

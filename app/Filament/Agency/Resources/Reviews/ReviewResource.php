<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Reviews;

use App\Filament\Agency\Resources\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|null|UnitEnum $navigationGroup = 'Retours';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static ?string $navigationLabel = 'Avis sur mes annonces';

    protected static ?string $modelLabel = 'Avis';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('ad', fn ($q) => $q->where('user_id', auth()->id()));
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->searchable(),
                TextColumn::make('user.fullname')
                    ->label('Client')
                    ->searchable(),
                TextColumn::make('comment')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Les agents ne gèrent pas (suppriment) les avis généralement
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}

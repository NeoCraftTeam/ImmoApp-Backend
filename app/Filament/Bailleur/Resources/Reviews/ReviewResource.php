<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Reviews;

use App\Filament\Bailleur\Resources\Reviews\Pages\ManageReviews;
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

    protected static ?string $navigationLabel = 'Avis clients';

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
                    ->badge()
                    ->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 2 ? 'warning' : 'danger')),
                TextColumn::make('ad.title')
                    ->label('Annonce'),
                TextColumn::make('user.fullname')
                    ->label('Client'),
                TextColumn::make('comment')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Posté le'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}

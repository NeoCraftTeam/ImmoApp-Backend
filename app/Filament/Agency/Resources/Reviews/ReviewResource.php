<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Reviews;

use App\Filament\Agency\Resources\Reviews\Pages\ManageReviews;
use App\Filament\Resources\Reviews\Concerns\SharedReviewResource;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class ReviewResource extends Resource
{
    use SharedReviewResource;

    protected static ?string $model = Review::class;

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static string|null|UnitEnum $navigationGroup = 'Retours';

    protected static ?string $modelLabel = 'Avis';

    protected static ?string $pluralModelLabel = 'Avis';

    protected static ?string $navigationLabel = 'Avis sur mes annonces';

    protected static function reviewBadgeCacheKeyPrefix(): string
    {
        return 'agency';
    }

    /**
     * @return array<Column>
     */
    protected static function additionalReviewTableColumns(): array
    {
        return [
            TextColumn::make('comment')
                ->label('Commentaire')
                ->limit(80)
                ->wrap()
                ->placeholder('—')
                ->tooltip(fn ($record) => $record->comment),
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}

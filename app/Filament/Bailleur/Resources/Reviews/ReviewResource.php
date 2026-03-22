<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Reviews;

use App\Filament\Bailleur\Resources\Reviews\Pages\ManageReviews;
use App\Filament\Resources\Reviews\Concerns\SharedReviewResource;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReviewResource extends Resource
{
    use SharedReviewResource;

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static string|null|UnitEnum $navigationGroup = 'Retours';

    protected static ?string $modelLabel = 'Avis';

    protected static ?string $pluralModelLabel = 'Avis';

    protected static ?string $navigationLabel = 'Avis clients';

    protected static ?int $navigationSort = 1;

    protected static function reviewBadgeCacheKeyPrefix(): string
    {
        return 'bailleur';
    }

    /**
     * @return array<string>
     */
    protected static function reviewUserEagerLoads(): array
    {
        return ['user.agency'];
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre d\'avis';
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}

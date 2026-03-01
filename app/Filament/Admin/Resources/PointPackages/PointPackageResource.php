<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PointPackages;

use App\Filament\Admin\Resources\PointPackages\Pages\CreatePointPackage;
use App\Filament\Admin\Resources\PointPackages\Pages\EditPointPackage;
use App\Filament\Admin\Resources\PointPackages\Pages\ListPointPackages;
use App\Filament\Admin\Resources\PointPackages\Schemas\PointPackageForm;
use App\Filament\Admin\Resources\PointPackages\Tables\PointPackagesTable;
use App\Models\PointPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PointPackageResource extends Resource
{
    protected static ?string $model = PointPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static \UnitEnum|string|null $navigationGroup = 'Système de Crédits';

    protected static ?string $navigationLabel = 'Packs de Crédits';

    protected static ?string $modelLabel = 'Pack de Crédits';

    protected static ?string $pluralModelLabel = 'Packs de Crédits';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return PointPackageForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return PointPackagesTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointPackages::route('/'),
            'create' => CreatePointPackage::route('/create'),
            'edit' => EditPointPackage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre de packs';
    }
}

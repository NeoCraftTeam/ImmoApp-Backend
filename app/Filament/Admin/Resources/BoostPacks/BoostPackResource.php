<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BoostPacks;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\BoostPacks\Pages\CreateBoostPack;
use App\Filament\Admin\Resources\BoostPacks\Pages\EditBoostPack;
use App\Filament\Admin\Resources\BoostPacks\Pages\ListBoostPacks;
use App\Filament\Admin\Resources\BoostPacks\Schemas\BoostPackForm;
use App\Filament\Admin\Resources\BoostPacks\Tables\BoostPacksTable;
use App\Models\BoostPack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BoostPackResource extends Resource
{
    protected static ?string $model = BoostPack::class;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::CreditsManage) ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static \UnitEnum|string|null $navigationGroup = 'Crédits';

    protected static ?string $navigationLabel = 'Packs Boost';

    protected static ?string $modelLabel = 'Pack Boost';

    protected static ?string $pluralModelLabel = 'Packs Boost';

    protected static ?int $navigationSort = 2;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return BoostPackForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return BoostPacksTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBoostPacks::route('/'),
            'create' => CreateBoostPack::route('/create'),
            'edit' => EditBoostPack::route('/{record}/edit'),
        ];
    }
}

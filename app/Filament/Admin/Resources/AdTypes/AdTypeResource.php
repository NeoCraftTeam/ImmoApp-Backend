<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdTypes;

use App\Filament\Admin\Resources\AdTypes\Pages\ListAdTypes;
use App\Filament\Admin\Resources\AdTypes\Schemas\AdTypeForm;
use App\Filament\Admin\Resources\AdTypes\Tables\AdTypesTable;
use App\Models\AdType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AdTypeResource extends Resource
{
    protected static ?string $model = AdType::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CursorArrowRipple;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Categories des annonces';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Catégorie d\'annonce';

    protected static ?string $pluralModelLabel = 'Catégories d\'annonces';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return AdTypeForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return AdTypesTable::configure($table)
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->successNotificationTitle('Type d\'annonce mis à jour'),
                DeleteAction::make()
                    ->successNotificationTitle('Type d\'annonce supprimé'),
            ]);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListAdTypes::route('/'),
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

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre de catégories';
    }
}

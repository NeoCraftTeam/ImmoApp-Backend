<?php

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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'type-annonce';

    public static function form(Schema $schema): Schema
    {
        return AdTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdTypesTable::configure($table)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdTypes::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

}

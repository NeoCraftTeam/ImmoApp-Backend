<?php

namespace App\Filament\Admin\Resources\Agencies;

use App\Filament\Admin\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Admin\Resources\Agencies\Pages\EditAgency;
use App\Filament\Admin\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Admin\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Admin\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Admin\Resources\Agencies\Schemas\AgencyInfolist;
use App\Filament\Admin\Resources\Agencies\Tables\AgenciesTable;
use App\Models\Agency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Agency';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return AgencyForm::configure($schema);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return AgencyInfolist::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return AgenciesTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencies::route('/'),
            'create' => CreateAgency::route('/create'),
            'view' => ViewAgency::route('/{record}'),
            'edit' => EditAgency::route('/{record}/edit'),
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

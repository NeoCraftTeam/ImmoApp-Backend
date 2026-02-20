<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributes;

use App\Filament\Admin\Resources\PropertyAttributes\Pages\CreatePropertyAttribute;
use App\Filament\Admin\Resources\PropertyAttributes\Pages\EditPropertyAttribute;
use App\Filament\Admin\Resources\PropertyAttributes\Pages\ListPropertyAttributes;
use App\Filament\Admin\Resources\PropertyAttributes\Schemas\PropertyAttributeForm;
use App\Filament\Admin\Resources\PropertyAttributes\Tables\PropertyAttributesTable;
use App\Models\PropertyAttribute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PropertyAttributeResource extends Resource
{
    protected static ?string $model = PropertyAttribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|null|\UnitEnum $navigationGroup = 'Configuration';

    protected static ?string $modelLabel = 'Attribut';

    protected static ?string $pluralModelLabel = 'Attributs';

    protected static ?int $navigationSort = 10;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return PropertyAttributeForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return PropertyAttributesTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropertyAttributes::route('/'),
            'create' => CreatePropertyAttribute::route('/create'),
            'edit' => EditPropertyAttribute::route('/{record}/edit'),
        ];
    }
}

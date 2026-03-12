<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PropertyAttributeCategories;

use App\Filament\Admin\Resources\PropertyAttributeCategories\Pages\CreatePropertyAttributeCategory;
use App\Filament\Admin\Resources\PropertyAttributeCategories\Pages\EditPropertyAttributeCategory;
use App\Filament\Admin\Resources\PropertyAttributeCategories\Pages\ListPropertyAttributeCategories;
use App\Filament\Admin\Resources\PropertyAttributeCategories\Schemas\PropertyAttributeCategoryForm;
use App\Filament\Admin\Resources\PropertyAttributeCategories\Tables\PropertyAttributeCategoriesTable;
use App\Models\PropertyAttributeCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PropertyAttributeCategoryResource extends Resource
{
    protected static ?string $model = PropertyAttributeCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|null|\UnitEnum $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Catégories attributs';

    protected static ?string $modelLabel = 'Catégorie attribut';

    protected static ?string $pluralModelLabel = 'Catégories attributs';

    protected static ?int $navigationSort = 0;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return PropertyAttributeCategoryForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return PropertyAttributeCategoriesTable::configure($table);
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
            'index' => ListPropertyAttributeCategories::route('/'),
            'create' => CreatePropertyAttributeCategory::route('/create'),
            'edit' => EditPropertyAttributeCategory::route('/{record}/edit'),
        ];
    }
}

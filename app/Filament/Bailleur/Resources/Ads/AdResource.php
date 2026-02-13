<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Ads;

use App\Filament\Bailleur\Resources\Ads\Pages\ManageAds;
use App\Filament\Resources\Ads\Concerns\SharedAdResource;
use App\Models\Ad;
use App\Models\Scopes\LandlordScope;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdResource extends Resource
{
    use SharedAdResource;

    protected static ?string $model = Ad::class;

    protected static string|null|UnitEnum $navigationGroup = 'Mes Biens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Mes Annonces';

    protected static ?string $modelLabel = 'Annonce';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withGlobalScope('landlord', new LandlordScope)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...static::getSharedFormFields(),
                static::getStatusSelect(isAdmin: false),
                DateTimePicker::make('expires_at'),
                ...static::getRelationSelects(),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(static::getSharedInfolistSchema());
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns(static::getSharedTableColumns())
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => static::mutateLocationMapData($data)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAds::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }
}

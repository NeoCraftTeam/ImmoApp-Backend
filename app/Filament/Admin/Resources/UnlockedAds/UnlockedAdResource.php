<?php

namespace App\Filament\Admin\Resources\UnlockedAds;

use App\Filament\Admin\Resources\UnlockedAds\Pages\ManageUnlockedAds;
use App\Filament\Exports\UnlockedAdExporter;
use App\Filament\Imports\UnlockedAdImporter;
use App\Models\UnlockedAd;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UnlockedAdResource extends Resource
{
    protected static ?string $model = UnlockedAd::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::LockOpen;

    protected static ?string $recordTitleAttribute = 'ad_id';

    protected static ?string $navigationLabel = 'Annonces débloquées';

    protected static ?string $modelLabel = 'Annonce débloquée';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ad_id')
                    ->relationship('ad', 'title')
                    ->required(),
                Select::make('user.fullname')
                    ->relationship('user', 'id')
                    ->required(),
                Select::make('payment_id')
                    ->relationship('payment', 'id')
                    ->required(),
                DateTimePicker::make('unlocked_at'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('ad.user.fullname')->label('Propriétaire'),
                TextEntry::make('ad.title')
                    ->label('Ad'),
                TextEntry::make('user.fullname')
                    ->label('Débloquée par'),
                TextEntry::make('payment.transaction_id')
                    ->label('Payment'),
                TextEntry::make('unlocked_at')
                    ->isoDate('LLLL', 'Europe/Paris'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (UnlockedAd $record): bool => $record->trashed()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('ad.user.fullname')->label('Propriétaire')
                    ->searchable(),
                TextColumn::make('ad.title')->label('Annonce')
                    ->searchable(),
                TextColumn::make('user.fullname')->label('Débloquée par')
                    ->searchable(),
                TextColumn::make('payment.transaction_id')->label('Payment ID')
                    ->searchable(),
                TextColumn::make('unlocked_at')
                    ->isoDate('LLLL', 'Europe/Paris')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label('Voir'),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->headerActions([

                ImportAction::make()->label('Importer')
                    ->importer(UnlockedAdImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(UnlockedAdExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUnlockedAds::route('/'),
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

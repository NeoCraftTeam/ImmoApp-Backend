<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UnlockedAds;

use App\Filament\Admin\Resources\UnlockedAds\Pages\ManageUnlockedAds;
use App\Filament\Exports\UnlockedAdExporter;
use App\Filament\Imports\UnlockedAdImporter;
use App\Models\UnlockedAd;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
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

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::LockOpen;

    protected static ?string $recordTitleAttribute = 'ad_id';

    protected static ?string $navigationLabel = 'Déblocages (Opérations)';

    protected static ?string $modelLabel = 'Annonce débloquée';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ad_id')
                    ->relationship('ad', 'title')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'firstname')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->firstname} {$record->lastname}")
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('payment_id')
                    ->relationship('payment', 'transaction_id')
                    ->searchable()
                    ->required(),
                DateTimePicker::make('unlocked_at'),
            ]);
    }

    #[\Override]
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
                    ->label('Payment ID'),
                TextEntry::make('unlocked_at')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (UnlockedAd $record): bool => $record->trashed()),
            ]);
    }

    #[\Override]
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
                    ->dateTime('d/m/Y H:i')
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
                ViewAction::make(),
            ])
            ->headerActions([

                ImportAction::make()->label('Importer')
                    ->importer(UnlockedAdImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(UnlockedAdExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                // Immutable records
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

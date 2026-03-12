<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdReports;

use App\Filament\Admin\Resources\AdReports\Pages\EditAdReport;
use App\Filament\Admin\Resources\AdReports\Pages\ListAdReports;
use App\Filament\Admin\Resources\AdReports\Schemas\AdReportForm;
use App\Filament\Admin\Resources\AdReports\Tables\AdReportsTable;
use App\Models\AdReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdReportResource extends Resource
{
    protected static ?string $model = AdReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Annonces';

    protected static ?string $navigationLabel = 'Signalements';

    protected static ?string $modelLabel = 'Signalement';

    protected static ?string $pluralModelLabel = 'Signalements';

    protected static ?int $navigationSort = 3;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return AdReportForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return AdReportsTable::configure($table);
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListAdReports::route('/'),
            'edit' => EditAdReport::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = AdReport::query()->where('status', \App\Enums\AdReportStatus::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }
}

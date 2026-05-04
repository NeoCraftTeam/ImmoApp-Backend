<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\LogsExportActivity;
use App\Models\City;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class CityExporter extends Exporter
{
    use LogsExportActivity;

    protected static ?string $model = City::class;

    protected static function humanModelLabel(): string
    {
        return 'villes';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Nom'),
            ExportColumn::make('created_at')->label('Créée le'),
            ExportColumn::make('updated_at')->label('Modifiée le'),
            ExportColumn::make('deleted_at')->label('Supprimée le'),
        ];
    }
}

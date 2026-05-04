<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\LogsExportActivity;
use App\Models\Quarter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class QuarterExporter extends Exporter
{
    use LogsExportActivity;

    protected static ?string $model = Quarter::class;

    protected static function humanModelLabel(): string
    {
        return 'quartiers';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Nom'),
            ExportColumn::make('city.name')->label('Ville'),
            ExportColumn::make('created_at')->label('Créé le'),
            ExportColumn::make('updated_at')->label('Modifié le'),
            ExportColumn::make('deleted_at')->label('Supprimé le'),
        ];
    }
}

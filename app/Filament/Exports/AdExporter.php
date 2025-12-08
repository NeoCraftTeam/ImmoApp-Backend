<?php

namespace App\Filament\Exports;

use App\Models\Ad;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class AdExporter extends Exporter
{
    protected static ?string $model = Ad::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('title'),
            ExportColumn::make('slug'),
            ExportColumn::make('description'),
            ExportColumn::make('adresse'),
            ExportColumn::make('price'),
            ExportColumn::make('surface_area'),
            ExportColumn::make('bedrooms'),
            ExportColumn::make('bathrooms'),
            ExportColumn::make('has_parking'),
            ExportColumn::make('location'),
            ExportColumn::make('status'),
            ExportColumn::make('expires_at'),
            ExportColumn::make('user.id'),
            ExportColumn::make('quarter.name'),
            ExportColumn::make('type_id'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('deleted_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your ad export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}

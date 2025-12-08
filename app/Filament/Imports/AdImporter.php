<?php

namespace App\Filament\Imports;

use App\Models\Ad;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class AdImporter extends Importer
{
    protected static ?string $model = Ad::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('title')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('slug')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('description')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('adresse')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('price')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('surface_area')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('bedrooms')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('bathrooms')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('has_parking')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('location'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('expires_at')
                ->rules(['datetime']),
            ImportColumn::make('user')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('quarter')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('type_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your ad import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }

    public function resolveRecord(): Ad
    {
        return Ad::firstOrNew([
            'slug' => $this->data['slug'],
        ]);
    }
}

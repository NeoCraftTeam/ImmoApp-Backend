<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Quarter;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class QuarterImporter extends Importer
{
    protected static ?string $model = Quarter::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('city')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your quarter import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    #[\Override]
    public function resolveRecord(): Quarter
    {
        return Quarter::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }
}

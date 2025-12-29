<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\UnlockedAd;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class UnlockedAdImporter extends Importer
{
    protected static ?string $model = UnlockedAd::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('ad')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('user')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('payment')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('unlocked_at')
                ->rules(['datetime']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your unlocked ad import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    #[\Override]
    public function resolveRecord(): UnlockedAd
    {
        return UnlockedAd::firstOrNew([
            'payment_id' => $this->data['payment_id'],
        ]);
    }
}

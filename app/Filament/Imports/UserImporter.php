<?php

namespace App\Filament\Imports;

use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('firstname')
                ->rules(['max:255']),
            ImportColumn::make('lastname')
                ->rules(['max:255']),
            ImportColumn::make('phone_number')
                ->rules(['max:255']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('email_verified_at')
                ->rules(['email', 'datetime']),
            ImportColumn::make('password')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('avatar')
                ->rules(['max:255']),
            ImportColumn::make('type')
                ->rules(['max:255']),
            ImportColumn::make('role')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('city')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('last_login_at')
                ->rules(['datetime']),
            ImportColumn::make('last_login_ip')
                ->rules(['max:255']),
            ImportColumn::make('is_active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('location'),
            ImportColumn::make('app_authentication_secret'),
            ImportColumn::make('app_authentication_recovery_codes'),
            ImportColumn::make('has_email_authentication')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'email', 'boolean']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your user import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    public function resolveRecord(): User
    {
        return User::firstOrNew([
            'email' => $this->data['email'],
        ]);
    }
}

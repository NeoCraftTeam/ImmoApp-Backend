<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\LogsExportActivity;
use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class PaymentExporter extends Exporter
{
    use LogsExportActivity;

    protected static ?string $model = Payment::class;

    protected static function humanModelLabel(): string
    {
        return 'paiements';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('type')
                ->label('Type')
                ->state(fn (Payment $record): ?string => $record->type?->value),
            ExportColumn::make('amount')
                ->label('Montant (XAF)'),
            ExportColumn::make('status')
                ->label('Statut')
                ->state(fn (Payment $record): ?string => $record->status?->value),
            ExportColumn::make('payment_method')
                ->label('Moyen de paiement')
                ->state(fn (Payment $record): ?string => $record->payment_method?->value),
            ExportColumn::make('gateway')
                ->label('Passerelle'),
            ExportColumn::make('transaction_id')
                ->label('Référence transaction'),
            ExportColumn::make('phone_number')
                ->label('Téléphone'),
            ExportColumn::make('user.fullname')
                ->label('Utilisateur'),
            ExportColumn::make('user.email')
                ->label('Email utilisateur'),
            ExportColumn::make('ad.title')
                ->label('Annonce'),
            ExportColumn::make('points_awarded')
                ->label('Crédits attribués'),
            ExportColumn::make('pointPackage.name')
                ->label('Pack'),
            ExportColumn::make('created_at')
                ->label('Créé le'),
            ExportColumn::make('updated_at')
                ->label('Modifié le'),
            ExportColumn::make('deleted_at')
                ->label('Supprimé le'),
        ];
    }
}

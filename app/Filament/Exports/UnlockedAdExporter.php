<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\LogsExportActivity;
use App\Models\UnlockedAd;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class UnlockedAdExporter extends Exporter
{
    use LogsExportActivity;

    protected static ?string $model = UnlockedAd::class;

    protected static function humanModelLabel(): string
    {
        return 'annonces débloquées';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('ad.title')->label('Annonce'),
            ExportColumn::make('user.fullname')->label('Utilisateur'),
            ExportColumn::make('user.email')->label('Email utilisateur'),
            ExportColumn::make('payment.amount')->label('Montant payé (XAF)'),
            ExportColumn::make('payment.transaction_id')->label('Référence paiement'),
            ExportColumn::make('unlocked_at')->label('Déblocage le'),
            ExportColumn::make('updated_at')->label('Modifié le'),
            ExportColumn::make('deleted_at')->label('Supprimé le'),
        ];
    }
}

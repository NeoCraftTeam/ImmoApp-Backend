<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\LogsExportActivity;
use App\Models\Ad;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class AdExporter extends Exporter
{
    use LogsExportActivity;

    protected static ?string $model = Ad::class;

    protected static function humanModelLabel(): string
    {
        return 'annonces';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('title')
                ->label('Titre'),
            ExportColumn::make('slug')
                ->label('Slug'),
            ExportColumn::make('description')
                ->label('Description'),
            ExportColumn::make('adresse')
                ->label('Adresse'),
            ExportColumn::make('price')
                ->label('Prix (XAF)'),
            ExportColumn::make('surface_area')
                ->label('Surface (m²)'),
            ExportColumn::make('bedrooms')
                ->label('Chambres'),
            ExportColumn::make('bathrooms')
                ->label('Salles de bain'),
            ExportColumn::make('has_parking')
                ->label('Parking')
                ->state(fn (Ad $record): string => $record->has_parking ? 'Oui' : 'Non'),
            ExportColumn::make('status')
                ->label('Statut')
                ->state(fn (Ad $record): string => $record->status->value),
            ExportColumn::make('is_visible')
                ->label('Visible')
                ->state(fn (Ad $record): string => $record->is_visible ? 'Oui' : 'Non'),
            ExportColumn::make('user.fullname')
                ->label('Propriétaire'),
            ExportColumn::make('user.email')
                ->label('Email propriétaire'),
            ExportColumn::make('user.phone_number')
                ->label('Téléphone propriétaire'),
            ExportColumn::make('quarter.city.name')
                ->label('Ville'),
            ExportColumn::make('quarter.name')
                ->label('Quartier'),
            ExportColumn::make('ad_type.name')
                ->label('Type'),
            ExportColumn::make('expires_at')
                ->label('Expire le'),
            ExportColumn::make('created_at')
                ->label('Créée le'),
            ExportColumn::make('updated_at')
                ->label('Modifiée le'),
            ExportColumn::make('deleted_at')
                ->label('Supprimée le'),
        ];
    }
}

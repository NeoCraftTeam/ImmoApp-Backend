<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\LogsExportActivity;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class UserExporter extends Exporter
{
    use LogsExportActivity;

    protected static ?string $model = User::class;

    protected static function humanModelLabel(): string
    {
        return 'utilisateurs';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('full_name')
                ->label('Nom complet')
                ->state(fn (User $record): string => trim($record->firstname.' '.$record->lastname)),
            ExportColumn::make('email')
                ->label('Email'),
            ExportColumn::make('phone_number')
                ->label('Téléphone'),
            ExportColumn::make('email_verified_at')
                ->label('Email vérifié le'),
            ExportColumn::make('type')
                ->label('Type')
                ->state(fn (User $record): ?string => $record->type?->value),
            ExportColumn::make('role')
                ->label('Rôle')
                ->state(fn (User $record): ?string => $record->role?->value),
            ExportColumn::make('is_active')
                ->label('Actif')
                ->state(fn (User $record): string => $record->is_active ? 'Oui' : 'Non'),
            ExportColumn::make('city.name')
                ->label('Ville'),
            ExportColumn::make('agency.name')
                ->label('Agence'),
            ExportColumn::make('point_balance')
                ->label('Solde crédits'),
            ExportColumn::make('locale')
                ->label('Langue'),
            ExportColumn::make('created_at')
                ->label('Inscrit le'),
            ExportColumn::make('last_login_at')
                ->label('Dernière connexion'),
            ExportColumn::make('last_login_ip')
                ->label('Dernière IP'),
            ExportColumn::make('acquisition_source')
                ->label('Canal acquisition'),
            ExportColumn::make('utm_source')
                ->label('UTM source'),
            ExportColumn::make('utm_medium')
                ->label('UTM medium'),
            ExportColumn::make('utm_campaign')
                ->label('UTM campagne'),
            ExportColumn::make('utm_content')
                ->label('UTM contenu'),
            ExportColumn::make('utm_term')
                ->label('UTM terme'),
            ExportColumn::make('referrer_domain')
                ->label('Domaine référent'),
            ExportColumn::make('updated_at')
                ->label('Modifié le'),
            ExportColumn::make('deleted_at')
                ->label('Supprimé le'),
        ];
    }
}

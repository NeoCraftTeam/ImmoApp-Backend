<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AcquisitionUsers\Pages;

use App\Filament\Admin\Resources\AcquisitionUsers\AcquisitionUserResource;
use App\Filament\Exports\UserExporter;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ManageRecords;

class ManageAcquisitionUsers extends ManageRecords
{
    protected static string $resource = AcquisitionUserResource::class;

    protected static ?string $title = 'Acquisition — utilisateurs inscrits';

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Exporter CSV')
                ->exporter(UserExporter::class)
                ->formats([ExportFormat::Csv]),
        ];
    }
}

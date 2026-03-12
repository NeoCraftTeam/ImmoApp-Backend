<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdReports\Pages;

use App\Enums\AdReportStatus;
use App\Filament\Admin\Resources\AdReports\AdReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdReport extends EditRecord
{
    protected static string $resource = AdReportResource::class;

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $nextStatus = AdReportStatus::from($data['status']);
        $isClosingStatus = in_array($nextStatus, [AdReportStatus::RESOLVED, AdReportStatus::DISMISSED], true);

        if ($isClosingStatus) {
            $data['resolved_at'] = now();
            $data['resolved_by'] = auth()->id();
        } else {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }

        return $data;
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

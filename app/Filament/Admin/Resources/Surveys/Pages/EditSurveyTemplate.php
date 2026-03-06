<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Surveys\Pages;

use App\Filament\Admin\Resources\Surveys\SurveyTemplateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSurveyTemplate extends EditRecord
{
    protected static string $resource = SurveyTemplateResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_survey')
                ->label('Voir le sondage')
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('gray')
                ->url(fn () => \App\Filament\Admin\Resources\Surveys\SurveyResource::getUrl('view', ['record' => $this->record]))
                ->openUrlInNewTab(false),
        ];
    }

    #[\Override]
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

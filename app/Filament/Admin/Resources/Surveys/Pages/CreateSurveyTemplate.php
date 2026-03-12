<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Surveys\Pages;

use App\Filament\Admin\Resources\Surveys\SurveyTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurveyTemplate extends CreateRecord
{
    protected static string $resource = SurveyTemplateResource::class;

    #[\Override]
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

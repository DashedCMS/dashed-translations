<?php

namespace Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource;

class ListAutomatedTranslationProgress extends ListRecords
{
    protected static string $resource = AutomatedTranslationProgressResource::class;

    protected function getTablePollingInterval(): ?string
    {
        return '1s';
    }
}

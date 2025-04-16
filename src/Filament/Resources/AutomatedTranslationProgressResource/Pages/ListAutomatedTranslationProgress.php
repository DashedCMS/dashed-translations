<?php

namespace Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages;

use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource;
use Filament\Resources\Pages\ListRecords;

class ListAutomatedTranslationProgress extends ListRecords
{
    protected static string $resource = AutomatedTranslationProgressResource::class;

    protected function getTablePollingInterval(): ?string
    {
        return '1s';
    }
}

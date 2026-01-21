<?php

namespace Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages;

use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages\Widgets\AutomatedTranslationStats;
use Filament\Resources\Pages\ListRecords;

class ListAutomatedTranslationProgress extends ListRecords
{
    protected static string $resource = AutomatedTranslationProgressResource::class;

    protected function getTablePollingInterval(): ?string
    {
        return '1s';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AutomatedTranslationStats::class,
        ];
    }
}

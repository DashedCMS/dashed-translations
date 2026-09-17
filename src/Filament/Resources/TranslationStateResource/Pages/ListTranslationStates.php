<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedTranslations\Classes\Translatables\RunningTranslations;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Widgets\TranslationStateStats;

class ListTranslationStates extends ListRecords
{
    protected static string $resource = TranslationStateResource::class;

    public function mount(): void
    {
        RunningTranslations::reset();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            TranslationStateResource::translateOutdatedAction(false),
            TranslationStateResource::translateOutdatedAction(true),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TranslationStateStats::class,
        ];
    }
}

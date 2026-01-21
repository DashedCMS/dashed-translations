<?php

namespace Dashed\DashedTranslations;

use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages\Widgets\AutomatedTranslationStats;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DashedTranslationsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-translations';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                TranslationsSettingsPage::class,
            ])
            ->widgets([
                AutomatedTranslationStats::class,
            ])
            ->resources([
                TranslationResource::class,
                AutomatedTranslationProgressResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}

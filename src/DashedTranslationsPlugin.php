<?php

namespace Dashed\DashedTranslations;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;
use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource;

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
            ->resources([
                TranslationResource::class,
                AutomatedTranslationProgressResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}

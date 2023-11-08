<?php

namespace Dashed\DashedTranslations;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;

class DashedTranslationsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-translations';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                TranslationResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}

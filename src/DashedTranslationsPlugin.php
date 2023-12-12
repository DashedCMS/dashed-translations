<?php

namespace Dashed\DashedTranslations;

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
            ->resources([
                TranslationResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}

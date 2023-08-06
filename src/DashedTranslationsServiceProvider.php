<?php

namespace Dashed\DashedTranslations;

use Dashed\DashedTranslations\Filament\Resources\TranslationResource;
use Filament\PluginServiceProvider;
use Spatie\LaravelPackageTools\Package;

class DashedTranslationsServiceProvider extends PluginServiceProvider
{
    public static string $name = 'dashed-translations';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('dashed-translations')
            ->hasViews();
    }

    protected function getResources(): array
    {
        return array_merge(parent::getResources(), [
            TranslationResource::class,
        ]);
    }
}

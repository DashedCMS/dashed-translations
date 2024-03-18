<?php

namespace Dashed\DashedTranslations;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;

class DashedTranslationsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-translations';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('dashed-translations')
            ->hasViews();
    }
}

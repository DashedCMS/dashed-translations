<?php

namespace Dashed\DashedTranslations;

<<<<<<< HEAD
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
=======
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;
use Filament\PluginServiceProvider;
use Spatie\LaravelPackageTools\Package;
>>>>>>> b79745b7f3b802274820f98a264a853f28cf727f

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

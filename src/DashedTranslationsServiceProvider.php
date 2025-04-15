<?php

namespace Dashed\DashedTranslations;

use Dashed\DashedTranslations\Commands\RemoveUnusedTranslations;
use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DashedTranslationsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-translations';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->registerSettingsPage(TranslationsSettingsPage::class, 'Vertalingen', 'language', 'Instellingen voor AI vertalingen');

        $package
            ->name('dashed-translations')
            ->hasCommands([
                RemoveUnusedTranslations::class,
            ])
            ->hasViews();

        cms()->builder('plugins', [
            new DashedTranslationsPlugin(),
        ]);
    }
}

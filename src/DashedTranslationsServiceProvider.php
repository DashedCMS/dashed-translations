<?php

namespace Dashed\DashedTranslations;

use Spatie\LaravelPackageTools\Package;
use Dashed\DashedCore\Support\MeasuresServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedTranslations\Commands\RemoveUnusedTranslations;
use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;

class DashedTranslationsServiceProvider extends PackageServiceProvider
{
    use MeasuresServiceProvider;
    public static string $name = 'dashed-translations';

    public function configurePackage(Package $package): void
    {
        $this->logProviderMemory('configurePackage:start');
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
        $this->logProviderMemory('configurePackage:end');
    }
}

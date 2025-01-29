<?php

namespace Dashed\DashedTranslations;

use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DashedTranslationsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-translations';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->builder(
            'settingPages',
            array_merge(cms()->builder('settingPages'), [
                'translations' => [
                    'name' => 'Vertalingen',
                    'description' => 'Instellingen voor AI vertalingen',
                    'icon' => 'language',
                    'page' => TranslationsSettingsPage::class,
                ],
            ])
        );

        $package
            ->name('dashed-translations')
            ->hasViews();

        cms()->builder('plugins', [
            new DashedTranslationsPlugin(),
        ]);
    }
}

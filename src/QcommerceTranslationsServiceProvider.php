<?php

namespace Qubiqx\QcommerceTranslations;

use Filament\PluginServiceProvider;
use Qubiqx\QcommerceTranslations\Filament\Resources\TranslationResource;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Qubiqx\QcommerceTranslations\Commands\QcommerceTranslationsCommand;

class QcommerceCoreServiceProvider extends PluginServiceProvider
{
    public static string $name = 'qcommerce-translations';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('qcommerce-translations')
            ->hasViews();
    }

    protected function getResources(): array
    {
        return array_merge(parent::getResources(), [
            TranslationResource::class,
        ]);
    }
}

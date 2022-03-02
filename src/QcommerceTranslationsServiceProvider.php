<?php

namespace Qubiqx\QcommerceTranslations;

use Filament\PluginServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Qubiqx\QcommerceTranslations\Filament\Resources\TranslationResource;

class QcommerceTranslationsServiceProvider extends PluginServiceProvider
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

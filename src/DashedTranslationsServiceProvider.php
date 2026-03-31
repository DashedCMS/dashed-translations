<?php

namespace Dashed\DashedTranslations;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedTranslations\Commands\RemoveUnusedTranslations;
use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages\Widgets\AutomatedTranslationStats;

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

    public function bootingPackage()
    {
        Livewire::component('automated-translation-stats', AutomatedTranslationStats::class);

        Gate::policy(\Dashed\DashedTranslations\Models\Translation::class, \Dashed\DashedTranslations\Policies\TranslationPolicy::class);
        Gate::policy(\Dashed\DashedTranslations\Models\AutomatedTranslationProgress::class, \Dashed\DashedTranslations\Policies\AutomatedTranslationProgressPolicy::class);

        cms()->registerRolePermissions('Vertalingen', [
            'edit_translation' => 'Vertalingen bewerken',
            'edit_automated_translation_progress' => 'Vertalingsvoortgang bewerken',
        ]);
    }
}

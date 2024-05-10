<?php

namespace Dashed\DashedTranslations\Filament\Pages\Settings;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TranslationsSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Vertalingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        $formData["deepl_translations_enabled"] = Customsetting::get('deepl_translations_enabled');
        $formData["deepl_api_key"] = Customsetting::get('deepl_api_key');

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $schema = [
            Toggle::make("deepl_translations_enabled")
                ->label('DeepL vertalingen')
                ->helperText('Deze functie is in BETA')
                ->reactive(),
            TextInput::make("deepl_api_key")
                ->label('DeepL API key')
                ->required(fn (Get $get) => $get('deepl_translations_enabled'))
                ->visible(fn (Get $get) => $get('deepl_translations_enabled')),
        ];

        return $schema;
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('deepl_translations_enabled', $this->form->getState()["deepl_translations_enabled"], $site['id']);
            Customsetting::set('deepl_api_key', $this->form->getState()["deepl_api_key"], $site['id']);
        }

        Notification::make()
            ->title('De vertaling instellingen zijn opgeslagen')
            ->success()
            ->send();

        return redirect(TranslationsSettingsPage::getUrl());
    }
}

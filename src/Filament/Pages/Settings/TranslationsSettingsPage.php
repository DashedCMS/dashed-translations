<?php

namespace Dashed\DashedTranslations\Filament\Pages\Settings;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class TranslationsSettingsPage extends Page implements HasSchemas
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use InteractsWithSchemas;
    use HasSettingsPermission;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Vertalingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        $formData["deepl_translations_enabled"] = Customsetting::get('deepl_translations_enabled');
        $formData["deepl_plan"] = Customsetting::get('deepl_plan', null, 'free');
        $formData["deepl_api_key"] = Customsetting::get('deepl_api_key');

        $formData['auto_translate_outdated'] = (bool) Customsetting::get('auto_translate_outdated');
        $formData['auto_translate_max_per_run'] = (int) Customsetting::get('auto_translate_max_per_run', null, 200);

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Toggle::make("deepl_translations_enabled")
                ->label(__('DeepL vertalingen'))
                ->helperText(__('Deze functie is in BETA'))
                ->reactive(),
            Select::make("deepl_plan")
                ->label(__('DeepL plan'))
                ->options([
                    'free' => __('Free API (api-free.deepl.com)'),
                    'pro' => __('Pro API (api.deepl.com)'),
                ])
                ->default('free')
                ->helperText(__('Free keys eindigen op ":fx". Kies het plan dat hoort bij je sleutel.'))
                ->required(fn (Get $get) => $get('deepl_translations_enabled'))
                ->visible(fn (Get $get) => $get('deepl_translations_enabled')),
            TextInput::make("deepl_api_key")
                ->label(__('DeepL API key'))
                ->required(fn (Get $get) => $get('deepl_translations_enabled'))
                ->visible(fn (Get $get) => $get('deepl_translations_enabled')),
            Section::make(__('Automatisch bijwerken'))
                ->description(__('Elke nacht om 03:00 worden ontbrekende en verouderde vertalingen opnieuw door de vertaler gehaald. Handmatig aangepaste vertalingen worden nooit automatisch overschreven; die zet je zelf opnieuw in gang op het scherm Vertaalstatus.'))
                ->schema([
                    // Deze twee velden staan in een sectie die verborgen is
                    // zolang DeepL uitstaat. Filament laat een verborgen
                    // component standaard uit getState() vallen, en dat werkt
                    // door naar de kinderen van een verborgen sectie; zonder
                    // dehydratedWhenHidden() zou submit() hier stil de
                    // standaardwaarden (uit, 200) wegschrijven zodra iemand
                    // opslaat met DeepL uit, en een ingestelde nachtronde
                    // met een afgestemd plafond verdwijnt dan ongemerkt.
                    // Daarom staat dehydratedWhenHidden() zowel op de sectie
                    // zelf als op de twee velden: alleen op de velden was niet
                    // genoeg, de uitsluiting van de verborgen ouder werkt door
                    // naar de kinderen. De twee DeepL-velden hierboven
                    // (deepl_plan, deepl_api_key) hebben dit bewust niet: dat
                    // is bestaand gedrag buiten deze taak en blijft
                    // ongewijzigd.
                    Toggle::make('auto_translate_outdated')
                        ->label(__('Ontbrekende en verouderde vertalingen \'s nachts automatisch bijwerken'))
                        ->dehydratedWhenHidden(),
                    TextInput::make('auto_translate_max_per_run')
                        ->label(__('Maximaal aantal items per nacht'))
                        ->helperText(__('Beperkt het DeepL-verbruik bij een grote catalogus. Wat niet aan de beurt komt, komt de volgende nacht.'))
                        ->numeric()
                        ->minValue(1)
                        ->default(200)
                        ->required()
                        ->dehydratedWhenHidden(),
                ])
                ->visible(fn (Get $get) => $get('deepl_translations_enabled'))
                ->dehydratedWhenHidden(),
        ])
            ->statePath('data');
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    protected function getActions(): array
    {
        return [
            Action::make('translationStates')
                ->label(__('Naar vertaalstatus'))
                ->icon('heroicon-o-clipboard-document-check')
                ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                ->url(TranslationStateResource::getUrl()),
        ];
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('deepl_translations_enabled', $this->form->getState()["deepl_translations_enabled"], $site['id']);
            Customsetting::set('deepl_plan', $this->form->getState()["deepl_plan"] ?? 'free', $site['id']);
            Customsetting::set('deepl_api_key', $this->form->getState()["deepl_api_key"] ?? '', $site['id']);
            Customsetting::set('auto_translate_outdated', (bool) ($this->form->getState()['auto_translate_outdated'] ?? false), $site['id']);
            Customsetting::set('auto_translate_max_per_run', (int) ($this->form->getState()['auto_translate_max_per_run'] ?? 200), $site['id']);
        }

        Notification::make()
            ->title(__('De vertaling instellingen zijn opgeslagen'))
            ->success()
            ->send();

        return redirect(TranslationsSettingsPage::getUrl());
    }
}

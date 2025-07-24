<?php

namespace Dashed\DashedTranslations\Filament\Pages\Settings;

use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;

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

    protected function getActions(): array
    {
        return [
            Action::make('bulkTranslate')
                ->label('Bulk vertalen')
                ->icon('heroicon-m-language')
                ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                ->form(function () {
                    $form = [];

                    foreach (cms()->builder('routeModels') as $routeModel) {
                        $form[] = Select::make($routeModel['name'])
                            ->label('Welke ' . str($routeModel['pluralName'])->lower() . ' wil je vertalen?')
                            ->options(function () use ($routeModel) {
                                $options = [];

                                foreach ($routeModel['class']::all()->pluck('name', 'id')->toArray() as $id => $name) {
                                    $options[$id] = $name;
                                }

                                return $options;
                            })
                            ->visible($routeModel['class']::all()->pluck('id')->count())
                            ->hintActions([
                                \Filament\Forms\Components\Actions\Action::make('select-all')
                                    ->label('Selecteer alles')
                                    ->action(function (Set $set) use ($routeModel) {
                                        $set($routeModel['name'], $routeModel['class']::all()->pluck('id')->toArray());
                                    }),
                            ])
                            ->multiple()
                            ->preload()
                            ->searchable();
                    }

                    $form[] =
                        Select::make('from_locale')
                            ->options(Locales::getLocalesArray())
                            ->default(Locales::getFirstLocale()['id'])
                            ->preload()
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->label('Vanaf taal')
                            ->afterStateUpdated(fn (Set $set, Get $get) => $set('to_locales', collect(Locales::getLocalesArrayWithoutCurrent($get('from_locale')))->keys()->toArray()));
                    $form[] =
                        Select::make('to_locales')
                            ->options(fn (Get $get) => Locales::getLocalesArrayWithoutCurrent($get('from_locale')))
                            ->default(fn (Get $get) => collect(Locales::getLocalesArrayWithoutCurrent($get('from_locale')))->keys()->toArray())
                            ->preload()
                            ->searchable()
                            ->required()
                            ->label('Naar talen')
                            ->reactive()
                            ->multiple();

                    return $form;
                })
                ->action(function (array $data) {
                    foreach ($data as $model => $results) {
                        foreach (cms()->builder('routeModels') as $routeModel) {
                            if ($model == $routeModel['name']) {
                                foreach ($results as $modelId) {
                                    AutomatedTranslation::translateModel($routeModel['class']::find($modelId), $data['from_locale'], $data['to_locales']);
                                }
                            }
                        }
                    }

                    Notification::make()
                        ->title('De vertalingen zijn gestart, dit kan even duren')
                        ->warning()
                        ->send();

                    //                    return redirect
                }),
        ];
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

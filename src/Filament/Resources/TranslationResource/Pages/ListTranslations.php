<?php

namespace Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedTranslations\Models\Translation;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Dashed\DashedTranslations\Jobs\TranslateValueFromModel;
use RalphJSmit\Filament\MediaLibrary\Forms\Components\MediaPicker;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource;

class ListTranslations extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string $resource = TranslationResource::class;
    protected string $view = 'dashed-translations::translations.pages.list-translations';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $translations = Translation::all();
        foreach ($translations as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if ($translation->type == 'datetime') {
                    $formData["translation_{$translation->id}_{$locale['id']}"] = Carbon::parse($translation->getTranslation('value', $locale['id']) ?: $translation->default)->format('Y-m-d H:i:s');
                } else {
                    $formData["translation_{$translation->id}_{$locale['id']}"] = $translation->getTranslation('value', $locale['id']);
                }
            }
        }

        $this->form->fill($formData);
    }

    protected function getActions(): array
    {

        return [
            self::translateEverything(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema($this->getFormSchema());
    }

    protected function getFormSchema(): array
    {
        global $component;
        $tabs = Translation::distinct('tag')->orderBy('tag', 'ASC')->pluck('tag');
        $sections = [];

        foreach ($tabs as $key => $tab) {
            $translations = Translation::where('tag', $tab)->orderBy('name', 'ASC')->get();
            $tabs = [];

            foreach (Locales::getLocales() as $locale) {
                $otherLocales = [];
                foreach (Locales::getLocales() as $localeLoop) {
                    if ($locale['id'] != $localeLoop['id']) {
                        $otherLocales[$localeLoop['id']] = $localeLoop['name'];
                    }
                }

                $schema = [];

                foreach ($translations as $translation) {
                    $helperText = '';
                    if ($translation->variables && is_array($translation->variables)) {
                        $helperText = 'Beschikbare variablen: ';

                        foreach ($translation->variables as $key => $value) {
                            $helperText .= ":$key: (bijv: $value)";

                            if ($key != array_key_last($translation->variables)) {
                                $helperText .= ', ';
                            }
                        }
                    }

                    if ($translation->type == 'textarea') {
                        $schema[] = Textarea::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder($translation->default)
                            ->rows(5)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->lazy()
                            ->hintAction(self::translateSingleField($otherLocales, $locale))
                            ->afterStateUpdated(function (Textarea $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    } elseif ($translation->type == 'datetime') {
                        $schema[] = DateTimePicker::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder(Carbon::parse($translation->default)->format('Y-m-d H:i:s'))
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->reactive()
                            ->afterStateUpdated(function (DateTimePicker $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    } elseif ($translation->type == 'editor') {
                        $schema[] = cms()->editorField("translation_{$translation->id}_{$locale['id']}", Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->live()
                            ->hintAction(self::translateSingleField($otherLocales, $locale))
                            ->afterStateUpdated(function (RichEditor $component, Set $set, $state) {
                                //                                $explode = explode('_', $component->getStatePath());
                                //                                $translationId = $explode[1];
                                //                                $locale = $explode[2];
                                //                                $translation = Translation::find($translationId);
                                //                                $translation->setTranslation("value", $locale, $state);
                                //                                $translation->save();
                                //                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                //                                Notification::make()
                                //                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                //                                    ->success()
                                //                                    ->send();
                            });
                    } elseif ($translation->type == 'image') {
                        $schema[] = mediaHelper()->field("translation_{$translation->id}_{$locale['id']}", Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->default($translation->default)
                            ->helperText($helperText ?? '')
                            ->hintAction(Action::make('save')
                                ->label('Media opslaan')
                                ->action(function ($state, $component) {
                                    $explode = explode('_', $component->getStatePath());
                                    $translationId = $explode[1];
                                    $locale = $explode[2];
                                    $translation = Translation::find($translationId);
                                    $translation->setTranslation("value", $locale, $state);
                                    $translation->save();
                                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                    Notification::make()
                                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                        ->success()
                                        ->send();
                                }))
                            ->afterStateUpdated(function (MediaPicker $component, $state) {
                                //                                $explode = explode('_', $component->getStatePath());
                                //                                $translationId = $explode[1];
                                //                                $locale = $explode[2];
                                //                                $translation = Translation::find($translationId);
                                //                                $translation->setTranslation("value", $locale, $state);
                                //                                $translation->save();
                                //                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                //                                Notification::make()
                                //                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                //                                    ->success()
                                //                                    ->send();
                            });
                    } elseif ($translation->type == 'repeater') {
                        $schema[] = Repeater::make("translation_{$translation->id}_{$locale['id']}")
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->schema(cms()->builder('translationRepeaters')[$translation->name] ?? [])
                            ->helperText($helperText ?? '')
                            ->reorderable()
                            ->cloneable()
                            ->reactive();
                    } elseif (in_array($translation->type, ['number', 'numeric'])) {
                        $schema[] = TextInput::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->lazy()
                            ->numeric()
//                            ->hintAction(self::translateSingleField($otherLocales, $locale))
                            ->afterStateUpdated(function (TextInput $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    } else {
                        $schema[] = TextInput::make("translation_{$translation->id}_{$locale['id']}")
                            ->placeholder($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->lazy()
                            ->hintAction(self::translateSingleField($otherLocales, $locale))
                            ->afterStateUpdated(function (TextInput $component, Set $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                                Notification::make()
                                    ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                                    ->success()
                                    ->send();
                            });
                    }
                }

                $tabs[] = Tab::make($locale['id'])
                    ->label(strtoupper($locale['id']))
                    ->schema($schema);
            }

            $sections[] = Section::make('Vertalingen voor ' . $tab)->columnSpanFull()
                ->schema([
                    Tabs::make('Locales')
                        ->tabs($tabs),
                ])
                ->headerActions([
                    self::translateTab($translations, $key),
                ])
                ->collapsible();
        }

        return $sections;
    }

    public function updated($path, $value): void
    {
        //        foreach (Translation::where('type', 'image')->get() as $translation) {
        //            foreach (Locales::getLocales() as $locale) {
        //                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
        //                    Notification::make()
        //                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
        //                        ->success()
        //                        ->send();
        //                }
        //            }
        //        }
        //
        //        dump($path, $value);

        foreach (Translation::where('type', 'editor')->get() as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
                    $value = $this->form->getState()["translation_{$translation->id}_{$locale['id']}"];
                    $explode = explode('.', $path);
                    $explode = explode('_', $explode[1]);
                    $translationId = $explode[1];
                    $locale = $explode[2];
                    $translation = Translation::find($translationId);
                    $translation->setTranslation("value", $locale, $value);
                    $translation->save();
                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                        ->success()
                        ->send();
                }
            }
        }

        foreach (Translation::where('type', 'repeater')->get() as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
                    $explode = explode('_', $path);
                    $translationId = $explode[1];
                    $locale = explode('.', $explode[2])[0];
                    $translation = Translation::find($translationId);
                    $translation->setTranslation("value", $locale, $this->data["translation_{$translation->id}_{$locale}"]);
                    $translation->save();
                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale . $translation->type));
                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen")
                        ->success()
                        ->send();
                }
            }
        }
    }

    public static function translateTab($translations, $key)
    {
        $translationSchema = [];

        foreach ($translations as $translation) {
            if (! in_array($translation->type, ['image', 'repeater'])) {
                $translationSchema[] = Toggle::make('translate.' . $translation->id)
                    ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                    ->default(true);
            }
        }

        $allLocales = [];

        foreach (Locales::getLocales() as $locale) {
            $allLocales[$locale['id']] = $locale['name'];
        }

        return
            Action::make('translateTab' . $key)
                ->icon('heroicon-m-language')
                ->label('Vertaal tab')
                ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                ->schema(array_merge([
                    Select::make('from_locale')
                        ->options($allLocales)
                        ->preload()
                        ->searchable()
                        ->required()
                        ->label('Vanaf taal'),
                    Select::make('to_locales')
                        ->options($allLocales)
                        ->preload()
                        ->searchable()
//                        ->default(collect($allLocales)->keys()->toArray())
                        ->required()
                        ->helperText('Zorg dat je niet de taal kiest waar het vandaag vertaald wordt')
                        ->label('Naar talen')
                        ->multiple(),
                ], $translationSchema))
                ->action(function (array $data) {
                    foreach ($data['translate'] as $id => $bool) {
                        if ($bool === true) {
                            $translation = Translation::find($id);
                            if (! $translation->getTranslation('value', $data['from_locale']) && $translation->default) {
                                $translation->setTranslation('value', $data['from_locale'], $translation->default);
                                $translation->save();
                            }
                            StartTranslationOfModel::dispatch($translation, $data['from_locale'], $data['to_locales']);
                            //                            $textToTranslate = $translation->getTranslation('value', $data['from_locale']) ?: $translation->default;
                            //                            foreach ($data['to_locales'] as $locale) {
                            //                                TranslateValueFromModel::dispatch($translation, 'value', $textToTranslate, $locale, $data['from_locale']);
                            //                            }
                        }
                    }

                    Notification::make()
                        ->title("De tab wordt vertaald")
                        ->success()
                        ->send();
                });
    }

    public static function translateEverything()
    {
        $tabs = Translation::distinct('tag')->orderBy('tag', 'ASC')->pluck('tag');
        $translationSchema = [];

        foreach ($tabs as $tab) {
            $translationSchema[] = Toggle::make('tabs.' . $tab)
                ->label('Vertaal tab ' . Str::of($tab)->replace('_', ' ')->replace('-', ' ')->title())
                ->default(true);
        }

        $allLocales = [];

        foreach (Locales::getLocales() as $locale) {
            $allLocales[$locale['id']] = $locale['name'];
        }

        return
            \Filament\Actions\Action::make('translateEverything')
                ->icon('heroicon-m-language')
                ->label('Vertaal alles')
                ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                ->schema(array_merge([
                    Select::make('from_locale')
                        ->options($allLocales)
                        ->preload()
                        ->searchable()
                        ->required()
                        ->label('Vanaf taal'),
                    Select::make('to_locales')
                        ->options($allLocales)
                        ->preload()
                        ->searchable()
//                        ->default(collect($allLocales)->keys()->toArray())
                        ->required()
                        ->helperText('Zorg dat je niet de taal kiest waar het vandaag vertaald wordt')
                        ->label('Naar talen')
                        ->multiple(),
                ], $translationSchema))
                ->action(function (array $data) use ($tabs) {
                    foreach ($data['tabs'] as $tab => $bool) {
                        if ($bool === true) {
                            $translations = Translation::where('tag', $tab)->get();
                            foreach ($translations as $translation) {
                                //                                $textToTranslate = $translation->getTranslation('value', $data['from_locale']) ?: $translation->default;
                                if (! $translation->getTranslation('value', $data['from_locale']) && $translation->default) {
                                    $translation->setTranslation('value', $data['from_locale'], $translation->default);
                                    $translation->save();
                                }
                                StartTranslationOfModel::dispatch($translation, $data['from_locale'], $data['to_locales']);
                                //                                    TranslateValueFromModel::dispatch($translation, 'value', $textToTranslate, $locale, $data['from_locale']);
                                //                                }
                            }
                        }
                    }

                    Notification::make()
                        ->title("Alles wordt vertaald")
                        ->success()
                        ->send();
                });
    }

    public static function translateSingleField($otherLocales, $locale)
    {
        return
            Action::make('translate')
                ->icon('heroicon-m-language')
                ->label('Vertaal')
                ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                ->schema([
                    Select::make('locales')
                        ->options($otherLocales)
                        ->preload()
                        ->searchable()
                        ->default(collect($otherLocales)->keys()->toArray())
                        ->required()
                        ->label('Talen')
                        ->multiple(),
                ])
                ->action(function (array $data, $livewire) use ($locale) {
                    $id = explode('_', $livewire->mountedActions[0]['context']['schemaComponent'])[1];
                    $translation = Translation::find($id);
                    if (! $translation->getTranslation('value', $locale['id']) && $translation->default) {
                        $translation->setTranslation('value', $locale['id'], $translation->default);
                        $translation->save();
                        Cache::forget(Str::slug($translation->name . $translation->tag . $locale['id'] . $translation->type));
                        $translation->refresh();
                    }
                    //                    $textToTranslate = $translation->getTranslation('value', $locale['id']) ?: $translation->default;
                    StartTranslationOfModel::dispatch($translation, $locale['id'], $data['locales']);
                    //                    foreach ($data['locales'] as $otherLocale) {
                    //                        TranslateValueFromModel::dispatch($translation, 'value', $textToTranslate, $otherLocale, $locale['id']);
                    //                    }

                    Notification::make()
                        ->title(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " wordt vertaald")
                        ->success()
                        ->send();
                });
    }
}

<?php

namespace Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages;

use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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

class ListTranslations extends ListRecords
{
    protected static string $resource = TranslationResource::class;

//    protected string $view = 'dashed-translations::translations.pages.list-translations';

    protected function getTableQuery(): Builder|Relation|null
    {
        return Translation::whereIn('id', function ($query) {
            $query->selectRaw('MAX(id)')
                ->from('dashed__translations')
                ->groupBy('tag');
        });
    }

    protected function getActions(): array
    {
        return [
            self::translateEverything(),
        ];
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
                                if (!$translation->getTranslation('value', $data['from_locale']) && $translation->default) {
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
}

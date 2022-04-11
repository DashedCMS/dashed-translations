<?php

namespace Qubiqx\QcommerceTranslations\Filament\Resources\TranslationResource\Pages;

use Closure;
use Illuminate\Support\Str;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Tabs;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Qubiqx\QcommerceCore\Classes\Locales;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Qubiqx\QcommerceTranslations\Filament\Resources\TranslationResource;

class ListTranslations extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TranslationResource::class;
    protected static string $view = 'qcommerce-translations::translations.pages.list-translations';
    public $data;

    public function mount(): void
    {
        $formData = [];
        $translations = Translation::all();
        foreach ($translations as $translation) {
            foreach (Locales::getLocales() as $locale) {
//                if ($translation->type == 'image') {
//                    $formData["translation_{$translation->id}_{$locale['id']}"] = [
//                        $translation->getTranslation('value', $locale['id'])
//                    ];
//                } else {
                $formData["translation_{$translation->id}_{$locale['id']}"] = $translation->getTranslation('value', $locale['id']);
//                }
            }
        }

        $this->form->fill($formData);
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    protected function getFormSchema(): array
    {
        $tags = Translation::distinct('tag')->orderBy('tag', 'ASC')->pluck('tag');
        $sections = [];

        foreach ($tags as $tag) {
            $translations = Translation::where('tag', $tag)->orderBy('name', 'ASC')->get();
            $tabs = [];

            foreach (Locales::getLocales() as $locale) {
                $schema = [];

                foreach ($translations as $translation) {
                    if ($translation->variables) {
                        $helperText = 'Beschikbare variablen: <br>';

                        foreach ($translation->variables as $key => $value) {
                            $helperText .= ":$key: (bijv: $value) <br>";
                        }
                    }

                    if ($translation->type == 'textarea') {
                        $schema[] = Textarea::make("translation_{$translation->id}_{$locale['id']}")
                            ->default($translation->default)
                            ->rows(5)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->reactive()
                            ->afterStateUpdated(function (Textarea $component, Closure $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale));
                                $this->notify('success', Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen");
                            });
                    } elseif ($translation->type == 'editor') {
                        $schema[] = TinyEditor::make("translation_{$translation->id}_{$locale['id']}")
                            ->fileAttachmentsDirectory('/qcommerce/orders/images')
                            ->default($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->reactive()
                            ->afterStateUpdated(function (TinyEditor $component, Closure $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale));
                                $this->notify('success', Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen");
                            });
                    } elseif ($translation->type == 'image') {
                        $schema[] = FileUpload::make("translation_{$translation->id}_{$locale['id']}")
                            ->disk('public')
                            ->default($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->reactive();
                    } else {
                        $schema[] = TextInput::make("translation_{$translation->id}_{$locale['id']}")
                            ->default($translation->default)
                            ->label(Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title())
                            ->helperText($helperText ?? '')
                            ->default($translation->getTranslation('value', $locale['id']))
                            ->reactive()
                            ->afterStateUpdated(function (TextInput $component, Closure $set, $state) {
                                $explode = explode('_', $component->getStatePath());
                                $translationId = $explode[1];
                                $locale = $explode[2];
                                $translation = Translation::find($translationId);
                                $translation->setTranslation("value", $locale, $state);
                                $translation->save();
                                Cache::forget(Str::slug($translation->name . $translation->tag . $locale));
                                $this->notify('success', Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen");
                            });
                    }
                }

                $tabs[] = Tab::make($locale['id'])
                    ->label(strtoupper($locale['id']))
                    ->schema($schema);
            }

            $sections[] = Section::make('Vertalingen voor ' . $tag)
                ->schema([
                    Tabs::make('Locales')
                        ->tabs($tabs),
                ])
                ->collapsible();
        }

        return $sections;
    }

    public function updated($path, $value): void
    {
        foreach (Translation::where('type', 'image')->get() as $translation) {
            foreach (Locales::getLocales() as $locale) {
                if (Str::contains($path, "translation_{$translation->id}_{$locale['id']}")) {
                    $this->notify('success', 'Afbeelding wordt opgeslagen');
                    $imagePath = $value->store('/qcommerce/translations', 'public');
                    $explode = explode('.', $path);
                    $explode = explode('_', $explode[1]);
                    $translationId = $explode[1];
                    $locale = $explode[2];
                    $translation = Translation::find($translationId);
                    $translation->setTranslation("value", $locale, $imagePath);
                    $translation->save();
                    Cache::forget(Str::slug($translation->name . $translation->tag . $locale));
                    $this->notify('success', Str::of($translation->name)->replace('_', ' ')->replace('-', ' ')->title() . " is opgeslagen");
                }
            }
        }
    }

//    public function submit()
//    {
//        $translations = Translation::all();
//        foreach ($translations as $translation) {
//            foreach (Locales::getLocales() as $locale) {
//                $translation->setTranslation("value", $locale['id'], $this->form->getState()["translation_{$translation->id}_{$locale['id']}"]);
//            }
//            $translation->save();
//        }
//
//        $this->notify('success', 'De vertalingen zijn opgeslagen');
//    }
}

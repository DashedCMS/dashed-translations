<?php

namespace Dashed\DashedTranslations\Jobs;

use Illuminate\Bus\Queueable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;

class ExtractStringsToTranslate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3000;
    public $tries = 1000;
    public $model;
    public $column;
    public $value;
    public $toLanguage;
    public $fromLanguage;
    public array $attributes;
    public AutomatedTranslationProgress $automatedTranslationProgress;

    /**
     * Create a new job instance.
     */
    public function __construct($model, $column, $value, $toLanguage, $fromLanguage, array $attributes = [], AutomatedTranslationProgress $automatedTranslationProgress)
    {
        $this->model = $model;
        $this->column = $column;
        $this->value = $value;
        $this->toLanguage = $toLanguage;
        $this->fromLanguage = $fromLanguage;
        $this->attributes = $attributes;
        $this->automatedTranslationProgress = $automatedTranslationProgress;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //        return;
        //        try {
        if ($this->toLanguage === $this->fromLanguage) {
            $this->automatedTranslationProgress->delete();

            return;
        }

        // NB: we deliberately do NOT copy the source value into the target
        // locale here. Doing so used to leave untranslated source text in the
        // target (e.g. French in the EN slot) whenever a translation failed to
        // complete. ReplaceStringsInModel now builds the target from the source
        // locale and only writes it once every string is translated.

        if (is_array($this->value)) {
            $this->searchAndTranslate(array: $this->value);
            //            $translatedText = $this->value;
        } else {
            $this->addString($this->value);
            //            $translatedText = $this->addString($this->value);
        }

        //        $this->automatedTranslationProgress->updateStats();

        if ($this->automatedTranslationProgress->total_strings_to_translate == 0) {
            $this->automatedTranslationProgress->status = 'finished';
            $this->automatedTranslationProgress->save();
        }

        //        } catch (\Exception $exception) {
        //            $this->failed($exception);
        //        }
    }

    //    public function failed($exception)
    //    {
    //        if (str($exception->getMessage())->contains('Too many requests')) {
    //            $this->automatedTranslationProgress->status = 'retrying';
    //            $this->automatedTranslationProgress->error = 'Opnieuw proberen i.v.m. rate limiting';
    //            $this->automatedTranslationProgress->save();
    //            ExtractStringsToTranslate::dispatch($this->model, $this->column, $this->value, $this->toLanguage, $this->fromLanguage, $this->attributes, $this->automatedTranslationProgress)
    //                ->delay(now()->addMinutes(2));
    //        } else {
    //            $this->automatedTranslationProgress->status = 'error';
    //            $this->automatedTranslationProgress->error = $exception->getMessage();
    //            $this->automatedTranslationProgress->save();
    //        }
    //    }

    /**
     * De sleutels die in deze kolom niet naar de vertaler mogen.
     *
     * De vaste sleutels horen bij de structuur van een builder-blok en gelden
     * overal. De lijst die een project zelf instelt geldt alleen voor kolommen
     * die builder-inhoud bevatten: daar beschermt hij sleutels als 'name' en
     * 'slug', die een blok identificeren in plaats van iets te tonen. In een
     * kolom met gewone inhoud betekent dezelfde sleutel juist wél tekst - denk
     * aan de opties van een keuzeveld, opgeslagen als [['name' => '...']] -
     * en knijpt de lijst de kolom leeg: er viel dan niets meer te vertalen.
     *
     * @return array<int, string>
     */
    private function ignorableKeys(): array
    {
        $fixedKeys = ['type', 'url', 'icon', 'background'];

        if ($this->columnHoldsPlainContent()) {
            return $fixedKeys;
        }

        return array_merge($fixedKeys, (array) cms()->builder('ignorableKeysForTranslations'));
    }

    /**
     * Of het model deze kolom heeft aangemeld als gewone inhoud in plaats van
     * builder-inhoud, via:
     *
     *     cms()->builder('plainContentColumnsForTranslations', [
     *         FormField::class => ['options', 'images'],
     *     ]);
     *
     * De vergelijking gaat via instanceof, zodat een project dat een
     * Dashed-model uitbreidt de registratie van de package erft.
     */
    private function columnHoldsPlainContent(): bool
    {
        foreach ((array) cms()->builder('plainContentColumnsForTranslations') as $model => $columns) {
            if ($this->model instanceof $model && in_array($this->column, (array) $columns, true)) {
                return true;
            }
        }

        return false;
    }

    private function searchAndTranslate(&$array, $parentKeys = [])
    {
        foreach ($array as $key => &$value) {
            if (! is_int($key) && $key != 'data') {
                $currentKeys = array_merge($parentKeys, [$key]);
            } else {
                $currentKeys = $parentKeys;
            }

            if (is_array($value)) {
                if (array_key_exists('type', $value) && array_key_exists('data', $value)) {
                    $currentKeys = array_merge($parentKeys, [$value['type']]);
                }
                $this->searchAndTranslate($value, $currentKeys);
            } elseif (! str($key)->contains($this->ignorableKeys()) && ! is_numeric($value) && ! is_int($value)) {
                $builderBlock = $this->matchBuilderBlock($key, $parentKeys, cms()->builder('blocks')) || $this->matchCustomBlock($key, $parentKeys, cms()->builder($this->attributes['customBlock'] ?? 'blocks'));
                if ($builderBlock && ($builderBlock instanceof Select || $builderBlock instanceof Toggle || $builderBlock instanceof FileUpload)) {
                    continue;
                }


                //                if($value == 'image'){
                //                    dump($value, $parentKeys, $key, $builderBlock, 'here');
                //                }
                $this->addString($value);
                //                $value = $this->addString($value);
            }
        }

        unset($value);
    }

    private function matchBuilderBlock($key, $parentKeys, $blocks, $currentBlock = null)
    {
        if (count($parentKeys) || (! count($parentKeys) && $currentBlock)) {
            foreach ($blocks as $block) {
                if (count($parentKeys) && method_exists($block, 'getName') && $block->getName() === $parentKeys[0]) {
                    $currentBlock = $block;
                } elseif ($currentBlock && method_exists($block, 'getName') && $block->getName() === $key) {
                    return $block;
                }
            }
        }

        if ($currentBlock && count($parentKeys)) {
            $currentBlock = $this->matchBuilderBlock($key, array_slice($parentKeys, 1), $this->childBlockComponents($currentBlock), $currentBlock);
        }

        if ($currentBlock && $currentBlock->getName() === $key) {
            return $currentBlock;
        }

        return null;
    }

    private function matchCustomBlock($key, $parentKeys, $blocks, $currentBlock = null)
    {
        if (count($parentKeys) || (! count($parentKeys) && $currentBlock)) {
            foreach ($blocks as $block) {
                if (count($parentKeys) && method_exists($block, 'getName') && $block->getName() === $parentKeys[0]) {
                    $currentBlock = $block;
                } elseif ($currentBlock && method_exists($block, 'getName') && $block->getName() === $key) {
                    return $block;
                }
            }
        }

        if ($currentBlock && count($parentKeys)) {
            $currentBlock = $this->matchCustomBlock($key, array_slice($parentKeys, 1), $this->childBlockComponents($currentBlock), $currentBlock);
        }

        if ($currentBlock && $currentBlock->getName() === $key) {
            return $currentBlock;
        }

        return null;
    }

    /**
     * Return a block's child components without requiring a Livewire container.
     *
     * Filament v4's getChildComponents() lazily builds a Schema that calls
     * getLivewire()/getContainer(), which standalone block definitions never
     * have set, throwing "Typed property ...Component::$container must not be
     * accessed before initialization". getDefaultChildComponents() returns the
     * configured child components without touching the container.
     */
    private function childBlockComponents($block): array
    {
        if (method_exists($block, 'getDefaultChildComponents')) {
            $components = $block->getDefaultChildComponents();

            if ($components instanceof \Filament\Schemas\Schema) {
                $components = $components->getComponents();
            }

            return is_array($components) ? $components : [];
        }

        return method_exists($block, 'getChildComponents') ? $block->getChildComponents() : [];
    }

    private function addString(?string $value = '')
    {
        // Whitespace-only values have nothing to translate. Registering them
        // poisons the column: the provider answers with an empty translation,
        // which the replace step reads as "not translated yet" and skips the
        // whole column on forever.
        if (AutomatedTranslationString::isUntranslatable($value)) {
            return $value;
        }

        // De kolom vergelijkt zonder hoofdletters en zonder spaties achteraan
        // (utf8mb4_unicode_ci), maar ReplaceStringsInModel vervangt letterlijk.
        // Een bestaande rij voor "Call Credit" zou ook "Call credit" bedienen,
        // waarna die tekst in de brontaal bleef staan. Daarom pas hergebruiken
        // bij een byte-exacte match.
        $string = AutomatedTranslationString::where('from_locale', $this->fromLanguage)
            ->where('to_locale', $this->toLanguage)
            ->where('from_string', $value)
            ->get()
            ->first(fn ($candidate) => $candidate->from_string === $value);

        if (! $string) {
            $string = new AutomatedTranslationString();
            $string->from_locale = $this->fromLanguage;
            $string->to_locale = $this->toLanguage;
            $string->from_string = $value;
            $string->save();
        }

        if (! $this->automatedTranslationProgress->strings()->where('automated_translation_string_id', $string->id)->wherePivot('column', $this->column)->exists()) {
            $this->automatedTranslationProgress->strings()->attach($string->id, [
                'column' => $this->column,
            ]);
            $this->automatedTranslationProgress->updateStats();
        }
        //        TranslateAndReplaceString::dispatch($string);
    }
}

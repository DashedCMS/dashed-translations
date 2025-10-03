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

        $this->model->setTranslation($this->column, $this->toLanguage, $this->model->getTranslation($this->column, $this->fromLanguage));
        $this->model->save();

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
            } elseif (! str($key)->contains(array_merge(['type', 'url', 'icon', 'background'], cms()->builder('ignorableKeysForTranslations'))) && ! is_numeric($value) && ! is_int($value)) {
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
            $currentBlock = $this->matchBuilderBlock($key, array_slice($parentKeys, 1), $currentBlock->getChildComponents(), $currentBlock);
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
            $currentBlock = $this->matchCustomBlock($key, array_slice($parentKeys, 1), $currentBlock->getChildComponents(), $currentBlock);
        }

        if ($currentBlock && $currentBlock->getName() === $key) {
            return $currentBlock;
        }

        return null;
    }

    private function addString(?string $value = '')
    {
        if (! $value) {
            return $value;
        }

        $string = AutomatedTranslationString::where('from_locale', $this->fromLanguage)
            ->where('to_locale', $this->toLanguage)
            ->where('from_string', $value)
            ->first();

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

<?php

namespace Dashed\DashedTranslations\Jobs;

use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslateValueFromModel implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 300;
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
        try {
            if ($this->toLanguage === $this->fromLanguage) {
                return;
            }

            if (is_array($this->value)) {
                $this->searchAndTranslate(array: $this->value);
                $translatedText = $this->value;
            } else {
                $translatedText = $this->translate($this->value);
            }

            $this->model->setTranslation($this->column, $this->toLanguage, $translatedText);
            $this->model->save();

            $this->automatedTranslationProgress->refresh();
            $this->automatedTranslationProgress->total_columns_translated++;
            $this->automatedTranslationProgress->save();
        } catch (\Exception $exception) {
            $this->failed($exception);
        }
    }

    public function failed($exception)
    {
        $this->automatedTranslationProgress->status = 'error';
        $this->automatedTranslationProgress->error = $exception->getMessage();
        $this->automatedTranslationProgress->save();

        if (str($this->automatedTranslationProgress->error)->contains('Too many requests')) {
            TranslateValueFromModel::dispatch($this->model, $this->column, $this->value, $this->toLanguage, $this->fromLanguage, $this->attributes, $this->automatedTranslationProgress)
                ->delay(now()->addMinutes(2));
        }
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
            } elseif (! str($key)->contains('type') && ! str($key)->contains('url')) {
                $builderBlock = $this->matchBuilderBlock($key, $parentKeys, cms()->builder('blocks')) || $this->matchCustomBlock($key, $parentKeys, cms()->builder($this->attributes['customBlock'] ?? 'blocks'));
                if ($builderBlock && ($builderBlock instanceof Select || $builderBlock instanceof Toggle || $builderBlock instanceof FileUpload)) {
                    continue;
                }

                $value = $this->translate($value);
            }
        }

        unset($value);
    }

    private function matchBuilderBlock($key, $parentKeys, $blocks, $currentBlock = null)
    {
        if (count($parentKeys) || (! count($parentKeys) && $currentBlock)) {
            foreach ($blocks as $block) {
                if (count($parentKeys) && $block->getName() === $parentKeys[0]) {
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
                if (count($parentKeys) && $block->getName() === $parentKeys[0]) {
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

    private function translate(?string $value = '')
    {
        if (! $value) {
            return $value;
        }

        return AutomatedTranslation::translate($value, $this->toLanguage, $this->fromLanguage);
    }
}

<?php

namespace Dashed\DashedTranslations\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Jobs\ExtractStringsToTranslate;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\Deepl\Facades\Deepl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class AutomatedTranslation
{
    public static function automatedTranslationsEnabled()
    {
        return ! is_null(self::getProvider());
    }

    public static function getProvider(): ?array
    {
        if (Customsetting::get('deepl_translations_enabled') && Customsetting::get('deepl_api_key')) {
            return [
                'provider' => 'deepl',
                'api_key' => Customsetting::get('deepl_api_key'),
            ];
        }

        return null;
    }

    public static function translate(string $text, string $targetLanguage, string $sourceLanguage): string
    {
        $provider = self::getProvider();

        if (! $provider) {
            throw new \Exception('No translation provider enabled');
        }

        if ($provider['provider'] === 'deepl') {
            Config::set('deepl.api_key', $provider['api_key']);

            return Deepl::api()->translate($text, $targetLanguage, $sourceLanguage);
        }
    }

    public static function translateModel(Model $model, string $fromLocale, array $toLocales, array $overwriteColumns = [], ?AutomatedTranslationProgress $automatedTranslationProgress = null): void
    {
        StartTranslationOfModel::dispatch($model, $fromLocale, $toLocales, $overwriteColumns, $automatedTranslationProgress);

        if ($model->metadata) {
            StartTranslationOfModel::dispatch($model->metadata, $fromLocale, $toLocales, $overwriteColumns, $automatedTranslationProgress);
//            $translatableMetaColumns = [
//                'title',
//                'description',
//            ];

//            foreach ($translatableMetaColumns as $column) {
//                //                    $totalStringsToTranslate++;
//                $textToTranslate = $model->metadata->getTranslation($column, $fromLocale);
//                foreach ($toLocales as $locale) {
//                    ExtractStringsToTranslate::dispatch($model->metadata, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
//                    //                            ->delay(now()->addMinutes($waitMinutes));
//                    //                        $waitMinutes++;
//                }
//            }
        }

        if ($model->customBlocks) {
            StartTranslationOfModel::dispatch($model->customBlocks, $fromLocale, $toLocales, $overwriteColumns, $automatedTranslationProgress);
//            $translatableCustomBlockColumns = [
//                'blocks',
//            ];

//            foreach ($translatableCustomBlockColumns as $column) {
//                //                    $totalStringsToTranslate++;
//                $textToTranslate = $model->customBlocks->getTranslation($column, $fromLocale);
//                foreach ($toLocales as $locale) {
//                    ExtractStringsToTranslate::dispatch($model->customBlocks, $column, $textToTranslate, $locale, $fromLocale, [
//                        'customBlock' => str($model::class . 'Blocks')->explode('\\')->last(),
//                    ], $automatedTranslationProgresses[$locale]);
//                    //                            ->delay(now()->addMinutes($waitMinutes));
//                    //                        $waitMinutes++;
//                }
//            }
        }
    }
}

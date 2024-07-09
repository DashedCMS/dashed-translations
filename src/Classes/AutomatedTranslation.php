<?php

namespace Dashed\DashedTranslations\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Jobs\TranslateValueFromModel;
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

    public static function translateModel(Model $model, string $fromLocale, array $toLocales): void
    {
        foreach ($model->translatable as $column) {
            if (! method_exists($model, $column)) {
                $textToTranslate = $model->getTranslation($column, $fromLocale);

                foreach ($toLocales as $locale) {
                    TranslateValueFromModel::dispatch($model, $column, $textToTranslate, $locale, $fromLocale);
                }
            }
        }

        if ($model->metadata) {
            $translatableMetaColumns = [
                'title',
                'description',
            ];

            foreach ($translatableMetaColumns as $column) {
                $textToTranslate = $model->metadata->getTranslation($column, $fromLocale);
                foreach ($toLocales as $locale) {
                    TranslateValueFromModel::dispatch($model->metadata, $column, $textToTranslate, $locale, $fromLocale);
                }
            }
        }

        if ($model->customBlocks) {
            $translatableCustomBlockColumns = [
                'blocks',
            ];

            foreach ($translatableCustomBlockColumns as $column) {
                $textToTranslate = $model->customBlocks->getTranslation($column, $fromLocale);
                foreach ($toLocales as $locale) {
                    TranslateValueFromModel::dispatch($model->customBlocks, $column, $textToTranslate, $locale, $fromLocale, [
                        'customBlock' => str($model::class . 'Blocks')->explode('\\')->last(),
                    ]);
                }
            }
        }
    }
}

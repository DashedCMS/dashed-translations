<?php

namespace Dashed\DashedTranslations\Classes;

use Dashed\Deepl\Facades\Deepl;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Dashed\DashedTranslations\Jobs\ExtractStringsToTranslate;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;

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

            [$protected, $tokens] = self::protectVariables($text);
            $translated = Deepl::api()->translate($protected, $targetLanguage, $sourceLanguage);

            return self::restoreVariables($translated, $tokens);
        }

        return $text;
    }

    /**
     * Vervang :varName: tokens door een placeholder die DeepL niet
     * interpreteert. Voorkomt dat dynamische variabelen als :firstName:
     * of :invoiceId: vertaald worden en uiteindelijk niet meer matchen
     * met OrderVariableReplacer en consorten.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected static function protectVariables(string $text): array
    {
        $tokens = [];
        $i = 0;

        $protected = preg_replace_callback(
            '/:([a-zA-Z][a-zA-Z0-9_]*):/',
            function (array $match) use (&$tokens, &$i): string {
                $key = '[DTV' . $i . ']';
                $tokens[$key] = $match[0];
                $i++;

                return $key;
            },
            $text
        );

        return [$protected ?? $text, $tokens];
    }

    /**
     * @param  array<string, string>  $tokens
     */
    protected static function restoreVariables(string $text, array $tokens): string
    {
        if (! $tokens) {
            return $text;
        }

        return str_replace(array_keys($tokens), array_values($tokens), $text);
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

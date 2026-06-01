<?php

namespace Dashed\DashedTranslations\Classes;

use ChrisKonnertz\DeepLy\DeepLy;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Dashed\DashedTranslations\Jobs\ExtractStringsToTranslate;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;

class AutomatedTranslation
{
    public const MAX_REQUEST_BYTES = 51200; // 50 KB veilig onder DeepL's per-request grens

    public static function automatedTranslationsEnabled()
    {
        return ! is_null(self::getProvider());
    }

    public static function getProvider(): ?array
    {
        // disableCache: voorkomt dat een long-running queue worker een verouderde
        // sleutel uit zijn process-local runtimeContextCache blijft gebruiken
        // nadat de admin de DeepL-key heeft geroteerd in een ander proces.
        $apiKey = Customsetting::get('deepl_api_key', null, null, null, 'default', true);
        $plan = Customsetting::get('deepl_plan', null, 'free', null, 'default', true);

        if (Customsetting::get('deepl_translations_enabled') && $apiKey) {
            return [
                'provider' => 'deepl',
                'api_key' => $apiKey,
                'plan' => in_array($plan, ['free', 'pro'], true) ? $plan : 'free',
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
            [$protected, $tokens] = self::protectVariables($text);
            $client = self::deeplyClient($provider['api_key'], $provider['plan']);

            $translated = '';
            foreach (self::chunkForRequest($protected) as $chunk) {
                $translated .= $client->translate($chunk, $targetLanguage, $sourceLanguage);
            }

            return self::restoreVariables($translated, $tokens);
        }

        return $text;
    }

    /**
     * Bouw een DeepLy client met expliciet gekozen endpoint. De vendor-library
     * leidt het endpoint normaal af uit de ":fx"-suffix; wij overschrijven dat
     * zodat de admin het plan (Free vs Pro) handmatig kan kiezen.
     */
    protected static function deeplyClient(string $apiKey, string $plan): DeepLy
    {
        $client = new DeepLy($apiKey);

        $ref = new \ReflectionProperty(DeepLy::class, 'apiBaseUrl');
        $ref->setValue(
            $client,
            $plan === 'pro' ? DeepLy::API_PRO_BASE_URL : DeepLy::API_FREE_BASE_URL
        );

        return $client;
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

    /**
     * Deel tekst op in stukken die elk onder $maxBytes blijven, zodat één
     * DeepL-request nooit de per-request grootte-limiet (HTTP 413) raakt.
     * Lossless: implode('', chunkForRequest($t)) === $t. Splitst op
     * natuurlijke grenzen en valt alleen als laatste redmiddel terug op een
     * harde byte-split.
     *
     * @return list<string>
     */
    public static function chunkForRequest(string $text, int $maxBytes = self::MAX_REQUEST_BYTES): array
    {
        if (strlen($text) <= $maxBytes) {
            return [$text];
        }

        foreach (["\n\n", "\n", ". ", " "] as $separator) {
            if (! str_contains($text, $separator)) {
                continue;
            }

            // Splits maar behoud de separator aan het eind van elk stuk
            // (behalve het laatste) zodat samenvoegen lossless is.
            $pieces = explode($separator, $text);
            $segments = [];
            foreach ($pieces as $index => $piece) {
                $segments[] = $index < count($pieces) - 1 ? $piece . $separator : $piece;
            }

            $chunks = [];
            $current = '';
            foreach ($segments as $segment) {
                if (strlen($segment) > $maxBytes) {
                    // Eén segment is zelf te groot: flush en recurse dieper.
                    if ($current !== '') {
                        $chunks[] = $current;
                        $current = '';
                    }
                    foreach (self::chunkForRequest($segment, $maxBytes) as $sub) {
                        $chunks[] = $sub;
                    }

                    continue;
                }

                if (strlen($current) + strlen($segment) > $maxBytes) {
                    $chunks[] = $current;
                    $current = '';
                }
                $current .= $segment;
            }
            if ($current !== '') {
                $chunks[] = $current;
            }

            return $chunks;
        }

        // Geen enkele separator aanwezig en nog te groot: harde byte-split.
        return self::splitByLength($text, $maxBytes);
    }

    /**
     * Harde byte-split als laatste redmiddel: knip op <= $maxBytes, maar
     * nooit midden in een UTF-8-teken en nooit binnen een [DTVn]-token.
     *
     * @return list<string>
     */
    private static function splitByLength(string $text, int $maxBytes): array
    {
        $chunks = [];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $size = min($maxBytes, $length - $offset);
            $cut = $offset + $size;

            if ($cut < $length) {
                // 1) Niet midden in een [DTVn]-token knippen.
                $lastOpen = strrpos(substr($text, $offset, $size), '[');
                if ($lastOpen !== false) {
                    $absOpen = $offset + $lastOpen;
                    $close = strpos($text, ']', $absOpen);
                    if ($close !== false && $close >= $cut) {
                        // Token loopt over de knip heen: knip vóór het token,
                        // of als het token aan het begin staat ná het token,
                        // zodat we altijd voortgang boeken.
                        $cut = $absOpen > $offset ? $absOpen : ($close + 1);
                    }
                }

                // 2) Niet midden in een UTF-8-teken knippen: schuif terug
                //    zolang we op een continuation-byte (10xxxxxx) staan.
                while ($cut > $offset + 1 && (ord($text[$cut]) & 0xC0) === 0x80) {
                    $cut--;
                }
            }

            $chunks[] = substr($text, $offset, $cut - $offset);
            $offset = $cut;
        }

        return $chunks;
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

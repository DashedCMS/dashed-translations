<?php

namespace Dashed\DashedTranslations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;

class ReplaceStringsInModel implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3000;
    public $tries = 1000;
    public AutomatedTranslationProgress $automatedTranslationProgress;

    /**
     * Create a new job instance.
     */
    public function __construct(AutomatedTranslationProgress $automatedTranslationProgress)
    {
        $this->automatedTranslationProgress = $automatedTranslationProgress;
    }

    //    /**
    //     * Middleware die overlap voorkomt per model_type + model_id.
    //     */
    //    public function middleware(): array
    //    {
    //        return [
    //            (new WithoutOverlapping($this->uniqueKey()))
    //                ->expireAfter(1800) // failsafe: 30 min
    //                ->dontRelease(),    // niet meteen herplannen; laat de worker 'm afhandelen
    //        ];
    //    }

    /**
     * Unieke sleutel voor zowel middleware als ShouldBeUnique*.
     */
    //    private function uniqueKey(): string
    //    {
    //        // compacte, stabiele key
    //        return sprintf(
    //            'replace:%s:%s',
    //            $this->automatedTranslationProgress->model_type,
    //            $this->automatedTranslationProgress->model_id
    //        );
    //    }

    /**
     * Voor ShouldBeUniqueUntilProcessing: dezelfde sleutel gebruiken.
     */
    //    public function uniqueId(): string
    //    {
    //        return $this->uniqueKey();
    //    }

    //    /**
    //     * (Optioneel) hoelang de uniqueness-lock mag blijven bestaan vóór start.
    //     */
    //    public $uniqueFor = 3600; // 1 uur

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (AutomatedTranslationProgress::where('model_type', $this->automatedTranslationProgress->model_type)
            ->where('model_id', $this->automatedTranslationProgress->model_id)
            ->where('status', '!=', 'finished')
            ->count()) {
            ReplaceStringsInModel::dispatch($this->automatedTranslationProgress)
                ->delay(now()->addSeconds(5));

            return;
        }

        $automatedTranslationProgresses = AutomatedTranslationProgress::where('model_type', $this->automatedTranslationProgress->model_type)
            ->where('model_id', $this->automatedTranslationProgress->model_id)
            ->where('status', 'finished')
            ->get();

        $model = $this->automatedTranslationProgress->model;
        $model->refresh();
        //        foreach ($automatedTranslationProgresses->pluck('to_locale')->toArray() as $locale) {
        //            $model->setTranslation($this->automatedTranslationProgress->strings()->first()->pivot->column, $locale, $model->getTranslation($this->automatedTranslationProgress->strings()->first()->pivot->column, $this->automatedTranslationProgress->from_locale));
        //        }
        //        $model->save();

        foreach ($automatedTranslationProgresses as $automatedTranslationProgress) {
            $strings = $automatedTranslationProgress->strings()
                ->orderByRaw('CHAR_LENGTH(COALESCE(from_string, "")) DESC')
                ->get();

            // Group by the column each string belongs to so we can write each
            // column atomically: a column is only written once *every* one of
            // its strings has a real translation. A partial or failed
            // translation therefore leaves the target locale's previous value
            // intact instead of stamping in untranslated source text.
            $stringsByColumn = $strings->groupBy(fn ($string) => $string->pivot->column);

            foreach ($stringsByColumn as $column => $columnStrings) {
                // Whitespace-only sources (e.g. a non-breaking space) can never
                // get a real translation back, so they must not count towards
                // "still untranslated" — otherwise a single stray U+00A0 in a
                // content block blocks that column forever. They also carry no
                // text to replace, so drop them from the replace loop entirely.
                $columnStrings = $columnStrings->reject(
                    fn ($string) => AutomatedTranslationString::isUntranslatable($string->from_string)
                );

                $hasUntranslated = $columnStrings->contains(
                    fn ($string) => $string->to_string === null || $string->to_string === ''
                );
                if ($hasUntranslated) {
                    // Leave this column for a later run; do not lock the pivots
                    // so the strings can still be corrected once translated.
                    continue;
                }

                // Build the translated value from the SOURCE locale so the
                // from_strings are always present to match and replace.
                $translatedValue = $model->getTranslation(
                    $column,
                    $automatedTranslationProgress->from_locale
                );

                foreach ($columnStrings as $automatedTranslationString) {
                    $translatedValue = $automatedTranslationProgress->recursiveReplace(
                        $translatedValue,
                        $automatedTranslationString->from_string,
                        $automatedTranslationString->to_string
                    );
                }

                $model->setTranslation(
                    $column,
                    $automatedTranslationProgress->to_locale,
                    $translatedValue
                );

                foreach ($columnStrings as $automatedTranslationString) {
                    if (! $automatedTranslationString->pivot->replaced) {
                        $automatedTranslationString->pivot->replaced = true;
                        $automatedTranslationString->pivot->save();
                    }
                }
            }

            $model->saveQuietly();
        }
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
}

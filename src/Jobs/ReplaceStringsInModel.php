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

        //        dd('asdf');
        foreach ($automatedTranslationProgresses as $automatedTranslationProgress) {
            //            foreach ($automatedTranslationProgress->strings as $automatedTranslationString) {
            $strings = $automatedTranslationProgress->strings()->orderByRaw('CHAR_LENGTH(COALESCE(from_string, "")) DESC')->get();
            foreach ($strings as $automatedTranslationString) {
                if (! $automatedTranslationString->pivot->replaced) {
                    $textToReplaceIn = $model->getTranslation(
                        $automatedTranslationString->pivot->column,
                        $automatedTranslationString->to_locale
                    );

                    $textToReplaceInString = $textToReplaceIn;
                    if (is_array($textToReplaceIn)) {
                        $textToReplaceInString = json_encode($textToReplaceIn);
                    }
                    if (str($textToReplaceInString)->contains('Norsup prefab pools') && str($automatedTranslationString->from_string)->contains('Norsup prefab pools') && $automatedTranslationProgress->to_locale == 'de') {
                        //                        dump($automatedTranslationProgress->id);
                        //                        dump($textToReplaceIn, $automatedTranslationString, $strings);
                        //                        foreach($strings as $string){
                        //                            dump($string);
                        //                            dump($string->id . ' - ' . $string->from_string . ' - replaced: ' . $string->pivot->replaced);
                        //                        }
                        //                        dd('done');
                    }

                    //                    if(is_array($textToReplaceIn)){
                    //                        dd($strings);
                    //                    }
                    $textToReplaceIn = $automatedTranslationProgress->recursiveReplace(
                        $textToReplaceIn,
                        $automatedTranslationString->from_string,
                        $automatedTranslationString->to_string ?: $automatedTranslationString->from_string
                    );

                    if (str($textToReplaceInString)->contains('Why choose Norsup') && str($automatedTranslationString->from_string)->contains('Why choose Norsup') && $automatedTranslationProgress->to_locale == 'de') {
                        //                        dd($textToReplaceIn);
                    }

                    $model->setTranslation(
                        $automatedTranslationString->pivot->column,
                        $automatedTranslationString->to_locale,
                        $textToReplaceIn
                    );

                    $automatedTranslationString->pivot->replaced = true;
                    $automatedTranslationString->pivot->save();
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

<?php

namespace Dashed\DashedTranslations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;

class TranslateString implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 300;
    public $tries = 10;
    public AutomatedTranslationString $automatedTranslationString;

    /**
     * Create a new job instance.
     */
    public function __construct(AutomatedTranslationString $automatedTranslationString)
    {
        $this->automatedTranslationString = $automatedTranslationString;
    }

    //    public function middleware(): array
    //    {
    //        $key = sprintf(
    //            'translate:%s:%s:%s',
    //            $this->automatedTranslationString->from_locale,
    //            $this->automatedTranslationString->to_locale,
    //            md5($this->automatedTranslationString->from_string) // kort & stabiel
    //        );
    //
    //        return [
    //            (new WithoutOverlapping($key))
    //                ->expireAfter(600)     // failsafe lock expiry (seconden)
    //                ->dontRelease(),       // niet direct terug in de queue duwen
    //        ];
    //    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (! $this->automatedTranslationString->translated) {
                if (is_numeric($this->automatedTranslationString->from_string) || is_bool($this->automatedTranslationString->from_string)) {
                    $this->automatedTranslationString->to_string = $this->automatedTranslationString->from_string;
                } else {
                    $this->automatedTranslationString->to_string = AutomatedTranslation::translate($this->automatedTranslationString->from_string, $this->automatedTranslationString->to_locale, $this->automatedTranslationString->from_locale);
                }
                $this->automatedTranslationString->translated = true;
                $this->automatedTranslationString->save();
            }

            foreach ($this->automatedTranslationString->progress as $automatedTranslationProgress) {
                $automatedTranslationProgress->updateStats();
            }
        } catch (\Exception $exception) {
            $this->failed($exception);
        }
    }

    public function failed($exception)
    {
        if (str($exception->getMessage())->contains('Too many requests')) {
            foreach ($this->automatedTranslationString->progress as $automatedTranslationProgress) {
                $automatedTranslationProgress->status = 'retrying';
                $automatedTranslationProgress->error = 'Opnieuw proberen i.v.m. rate limiting';
                $automatedTranslationProgress->save();
            }
            TranslateAndReplaceString::dispatch($this->automatedTranslationString)
                ->delay(now()->addMinutes(2));
        } else {
            foreach ($this->automatedTranslationString->progress as $automatedTranslationProgress) {
                $automatedTranslationProgress->status = 'error';
                $automatedTranslationProgress->error = $exception->getMessage();
                $automatedTranslationProgress->save();
            }
        }
    }
}

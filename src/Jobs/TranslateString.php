<?php

namespace Dashed\DashedTranslations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;

class TranslateString implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HandlesQueueFailures;

    public AutomatedTranslationString $automatedTranslationString;

    /**
     * Override the trait defaults via methods (Laravel reads these and
     * they take precedence over the trait $tries/$timeout properties).
     * This avoids the PHP trait composition conflict that fires when
     * both trait and class declare the same property with different
     * defaults / type hints.
     */
    public function tries(): int
    {
        return 10;
    }

    public function timeout(): int
    {
        return 300;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->automatedTranslationString->id;
    }

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
            TranslateString::dispatch($this->automatedTranslationString)
                ->delay(now()->addMinutes(2));
        } else {
            foreach ($this->automatedTranslationString->progress as $automatedTranslationProgress) {
                $automatedTranslationProgress->status = 'error';
                $automatedTranslationProgress->error = $exception->getMessage();
                $automatedTranslationProgress->save();
            }
            // Rate-limit retries are expected - skip admin alert in that branch.
            // This else branch is a real terminal failure so notify admins.
            $this->reportFailure($exception);
        }
    }
}

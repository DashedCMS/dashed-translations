<?php

namespace Dashed\DashedTranslations\Jobs;

use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartTranslationOfModel implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3000;
    public $tries = 10;
    public $model;
    public $fromLocale;
    public $toLocales;
    public $overwriteColumns;
    public ?AutomatedTranslationProgress $automatedTranslationProgress;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model, string $fromLocale, array $toLocales, array $overwriteColumns = [], ?AutomatedTranslationProgress $automatedTranslationProgress = null)
    {
        $this->model = $model;
        $this->fromLocale = $fromLocale;
        $this->toLocales = $toLocales;
        $this->overwriteColumns = $overwriteColumns;
        $this->automatedTranslationProgress = $automatedTranslationProgress;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $model = $this->model;
        $fromLocale = $this->fromLocale;
        $toLocales = $this->toLocales;
        $overwriteColumns = $this->overwriteColumns;
        $automatedTranslationProgress = $this->automatedTranslationProgress;

        //        try {
        //            $waitMinutes = 0;

        //            $totalStringsToTranslate = 0;
        if ($automatedTranslationProgress) {
            $automatedTranslationProgress->status = 'in_progress';
            $automatedTranslationProgress->total_strings_to_translate = 0;
            $automatedTranslationProgress->total_strings_translated = 0;
            $automatedTranslationProgress->error = null;
            $automatedTranslationProgress->save();
        }

        $automatedTranslationProgresses = [];

        if (count($toLocales) == 1 && $automatedTranslationProgress) {
            $automatedTranslationProgresses[$toLocales[array_key_first($toLocales)]] = $automatedTranslationProgress;
        } else {
            foreach ($toLocales as $toLocale) {
                $automatedTranslationProgress = AutomatedTranslationProgress::where('model_type', $model::class)
                    ->where('model_id', $model->id)
                    ->where('from_locale', $fromLocale)
                    ->where('to_locale', $toLocale)
                    ->where('status', '!=', 'finished')
                    ->latest()
                    ->first();
                if (! $automatedTranslationProgress) {
                    $automatedTranslationProgress = new AutomatedTranslationProgress();
                    $automatedTranslationProgress->model_type = $model::class;
                    $automatedTranslationProgress->model_id = $model->id;
                    $automatedTranslationProgress->from_locale = $fromLocale;
                    $automatedTranslationProgress->to_locale = $toLocale;
                }
                $automatedTranslationProgress->status = 'in_progress';
                $automatedTranslationProgress->error = null;
                $automatedTranslationProgress->total_strings_to_translate = 0;
                $automatedTranslationProgress->total_strings_translated = 0;
                $automatedTranslationProgress->save();
                $automatedTranslationProgresses[$toLocale] = $automatedTranslationProgress;
            }
        }

        foreach ($model->translatable as $column) {
            if (! method_exists($model, $column) || in_array($column, $overwriteColumns)) {
                //                    $totalStringsToTranslate++;
                $textToTranslate = $model->getTranslation($column, $fromLocale);

                foreach ($toLocales as $locale) {
                    ExtractStringsToTranslate::dispatch($model, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
                    //                            ->delay(now()->addMinutes($waitMinutes));
                    //                        $waitMinutes++;
                }
            }
        }

        //        if ($model->metadata) {
        //            $translatableMetaColumns = [
        //                'title',
        //                'description',
        //            ];
        //
        //            foreach ($translatableMetaColumns as $column) {
        //                //                    $totalStringsToTranslate++;
        //                $textToTranslate = $model->metadata->getTranslation($column, $fromLocale);
        //                foreach ($toLocales as $locale) {
        //                    ExtractStringsToTranslate::dispatch($model->metadata, $column, $textToTranslate, $locale, $fromLocale, [], $automatedTranslationProgresses[$locale]);
        //                    //                            ->delay(now()->addMinutes($waitMinutes));
        //                    //                        $waitMinutes++;
        //                }
        //            }
        //        }
        //
        //        if ($model->customBlocks) {
        //            $translatableCustomBlockColumns = [
        //                'blocks',
        //            ];
        //
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
        //        }

        //            foreach ($toLocales as $toLocale) {
        //                $automatedTranslationProgress = AutomatedTranslationProgress::where('model_type', $model::class)
        //                    ->where('model_id', $model->id)
        //                    ->where('from_locale', $fromLocale)
        //                    ->where('to_locale', $toLocale)
        //                    ->latest()
        //                    ->first();
        //                $automatedTranslationProgress->total_strings_to_translate = $totalStringsToTranslate;
        //                $automatedTranslationProgress->save();
        //            }
        //        } catch (\Exception $exception) {
        //            $this->failed($exception);
        //        }
    }

    public function failed($exception)
    {
        //        dd($exception->getMessage());
        if (str($exception->getMessage())->contains('Too many requests')) {
            $this->automatedTranslationProgress->status = 'retrying';
            $this->automatedTranslationProgress->error = 'Opnieuw proberen i.v.m. rate limiting';
            $this->automatedTranslationProgress->save();
            StartTranslationOfModel::dispatch($this->model, $this->column, $this->value, $this->toLanguage, $this->fromLanguage, $this->attributes, $this->automatedTranslationProgress)
                ->delay(now()->addMinutes(2));
        } else {
            $this->automatedTranslationProgress->status = 'error';
            $this->automatedTranslationProgress->error = $exception->getMessage();
            $this->automatedTranslationProgress->save();
        }
    }
}

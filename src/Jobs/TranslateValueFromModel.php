<?php

namespace Dashed\DashedTranslations\Jobs;

use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Exception;
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

    /**
     * Create a new job instance.
     */
    public function __construct($model, $column, $value, $toLanguage, $fromLanguage)
    {
        $this->model = $model;
        $this->column = $column;
        $this->value = $value;
        $this->toLanguage = $toLanguage;
        $this->fromLanguage = $fromLanguage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->toLanguage === $this->fromLanguage) {
            return;
        }

        $translatedText = AutomatedTranslation::translate($this->value, $this->toLanguage, $this->fromLanguage);
        $this->model->setTranslation($this->column, $this->toLanguage, $translatedText);
        $this->model->save();
    }

    public function failed($exception)
    {
        throw new Exception('Translation failed: ' . $exception->getMessage());
    }
}

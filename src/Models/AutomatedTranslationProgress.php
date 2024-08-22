<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;

class AutomatedTranslationProgress extends Model
{
    protected $table = 'dashed__automated_translation_progress';

    public static function booted()
    {
        static::saved(function (AutomatedTranslationProgress $automatedTranslationProgress) {
            if($automatedTranslationProgress->total_columns_to_translate == $automatedTranslationProgress->total_columns_translated) {
                $automatedTranslationProgress->status = 'finished';
            } elseif($automatedTranslationProgress->total_columns_translated > 0) {
                $automatedTranslationProgress->status = 'in_progress';
            }
            $automatedTranslationProgress->saveQuietly();
        });
    }
}

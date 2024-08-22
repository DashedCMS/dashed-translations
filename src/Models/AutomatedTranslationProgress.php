<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class AutomatedTranslationProgress extends Model
{
    protected $table = 'dashed__automated_translation_progress';

    public static function booted()
    {
        static::saved(function (AutomatedTranslationProgress $automatedTranslationProgress) {
            if($automatedTranslationProgress->total_columns_to_translate == $automatedTranslationProgress->total_columns_translated){
                $this->status = 'finished';
            }elseif($automatedTranslationProgress->total_columns_translated > 0){
                $this->status = 'in_progress';
            }
            $this->saveQuietly();
        });
    }
}

<?php

namespace Dashed\DashedTranslations\Models;

use Dashed\DashedTranslations\Jobs\ReplaceStringsInModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class AutomatedTranslationProgress extends Model
{
    protected $table = 'dashed__automated_translation_progress';

    public static function booted()
    {
        static::saved(function (AutomatedTranslationProgress $automatedTranslationProgress) {
            //            $automatedTranslationProgress->updateStats();
        });
    }

    public function model()
    {
        return $this->morphTo();
    }

    public function strings(): BelongsToMany
    {
        return $this->belongsToMany(AutomatedTranslationString::class, 'dashed__automated_translation_progress_string')
            ->withPivot('replaced', 'column');
    }

    public function updateStats($startReplacingStrings = false)
    {
        $this->total_strings_to_translate = $this->strings->count();
        $totalStringsTranslated = 0;

        foreach ($this->strings as $string) {
            if ($string->translated) {
                $totalStringsTranslated++;
            }
        }
        $this->total_strings_translated = $totalStringsTranslated;

        if ($this->total_strings_to_translate == $this->total_strings_translated) {
//        if ($this->total_strings_to_translate > 0 && $this->total_strings_to_translate == $this->total_strings_translated) {
            $this->status = 'finished';
        } elseif ($this->total_strings_translated > 0) {
            $this->status = 'in_progress';
        } else {
            $this->status = 'pending';
        }
        $this->saveQuietly();

        if ($startReplacingStrings && $this->status == 'finished') {
            if (!self::where('model_type', $this->model_type)->where('model_id', $this->model_id)->where('status', '!=', 'finished')->count()) {
                dd('asdf');
//                ReplaceStringsInModel::dispatch($this);
            }
        }
    }

    public function recursiveReplace($subject, string $search, string $replace)
    {
        if (is_array($subject)) {
            return array_map(function ($item) use ($search, $replace) {
                return $this->recursiveReplace($item, $search, $replace);
            }, $subject);
        }

        if (is_string($subject)) {
            if((str($subject)->contains('choose Norsup prefab pools') || str($search)->contains('choose Norsup prefab pools')) && $this->to_locale == 'de'){
//                dump($subject, $search, $replace, str($subject)->replace($search, $replace)->toString());
            }
            return str($subject)->replace($search, $replace)->toString();
        }

        return $subject;
    }
}

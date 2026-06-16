<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
            // Only count a string as translated when it actually has a
            // non-empty translation. A string flagged translated=1 with an
            // empty to_string used to push progress to 100% / 'finished' while
            // the content was still untranslated, letting the replace step run
            // before any real translation existed.
            if ($string->translated && $string->to_string !== null && $string->to_string !== '') {
                $totalStringsTranslated++;
            }
        }
        $this->total_strings_translated = $totalStringsTranslated;

        if ($this->total_strings_to_translate == $this->total_strings_translated) {
            $this->status = 'finished';
        } elseif ($this->total_strings_translated > 0) {
            $this->status = 'in_progress';
        } else {
            $this->status = 'pending';
        }
        $this->saveQuietly();
    }

    public function recursiveReplace($subject, string $search, string $replace)
    {
        if (is_array($subject)) {
            return array_map(function ($item) use ($search, $replace) {
                return $this->recursiveReplace($item, $search, $replace);
            }, $subject);
        }

        if (is_string($subject)) {
            if ((str($subject)->contains('choose Norsup prefab pools') || str($search)->contains('choose Norsup prefab pools')) && $this->to_locale == 'de') {
                //                dump($subject, $search, $replace, str($subject)->replace($search, $replace)->toString());
            }

            return str($subject)->replace($search, $replace)->toString();
        }

        return $subject;
    }
}

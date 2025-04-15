<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomatedTranslationProgress extends Model
{
    protected $table = 'dashed__automated_translation_progress';

    public static function booted()
    {
        static::saved(function (AutomatedTranslationProgress $automatedTranslationProgress) {
            $automatedTranslationProgress->updateStats();
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

    public function updateStats()
    {
        $this->total_strings_to_translate = $this->strings->count();
        $totalStringsTranslated = 0;

        foreach ($this->strings as $string) {
            if ($string->translated) {
                $totalStringsTranslated++;
            }
        }
        $this->total_strings_translated = $totalStringsTranslated;

        if ($this->total_strings_to_translate > 0 && $this->total_strings_to_translate == $this->total_strings_translated) {
            $this->status = 'finished';
        } elseif ($this->total_strings_translated > 0) {
            $this->status = 'in_progress';
        } else {
            $this->status = 'pending';
        }
        $this->saveQuietly();
    }
}

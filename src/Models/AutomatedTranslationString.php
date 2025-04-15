<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AutomatedTranslationString extends Model
{
    protected $table = 'dashed__automated_translation_strings';

    public static function booted()
    {

    }

    public function progress(): BelongsToMany
    {
        return $this->belongsToMany(AutomatedTranslationProgress::class, 'dashed__automated_translation_progress_string')
            ->withPivot('replaced', 'column');
    }
}

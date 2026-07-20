<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AutomatedTranslationString extends Model
{
    protected $table = 'dashed__automated_translation_strings';

    protected $guarded = [];

    protected $casts = [
        'translated' => 'boolean',
    ];

    public static function booted()
    {

    }

    /**
     * A value that carries no translatable text and must never be registered as
     * a translatable string.
     *
     * PHP's trim() only strips ASCII whitespace, so a non-breaking space
     * (U+00A0, bytes C2A0) — routinely produced by pasting from Word or by the
     * rich editor itself — is both truthy and non-trim-empty. Such a value used
     * to be registered as a translatable string; the provider returns an empty
     * translation for it, and that empty translation then permanently blocked
     * its entire column from being written to the target locale. Match Unicode
     * separators and control/format characters, not just ASCII space.
     */
    public static function isUntranslatable(?string $value): bool
    {
        return $value === null || preg_match('/^[\p{Z}\p{C}\s]*$/u', $value) === 1;
    }

    public function progress(): BelongsToMany
    {
        return $this->belongsToMany(AutomatedTranslationProgress::class, 'dashed__automated_translation_progress_string')
            ->withPivot('replaced', 'column');
    }
}

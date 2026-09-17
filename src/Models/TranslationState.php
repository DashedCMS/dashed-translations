<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per model, brontaal en doeltaal: hoe de inhoud eruitzag toen er voor het
 * laatst vertaald werd, en wat de laatste vergelijking daarmee opleverde.
 * Dit is de staat van de inhoud; AutomatedTranslationProgress is de staat
 * van een lopende vertaaljob. Die twee blijven bewust gescheiden.
 */
class TranslationState extends Model
{
    public const STATUS_MISSING = 'missing';
    public const STATUS_TRANSLATED = 'translated';
    public const STATUS_OUTDATED = 'outdated';
    public const STATUS_MANUAL = 'manual';

    protected $table = 'dashed__translation_states';

    protected $guarded = [];

    protected $casts = [
        'source_changed' => 'boolean',
        'target_changed' => 'boolean',
        'complete' => 'boolean',
        'translated_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public function scopeVoorModel(Builder $query, Model $model): Builder
    {
        return $query->where('model_type', $model::class)->where('model_id', $model->getKey());
    }

    /**
     * De volgorde is de kern: ontbreken gaat voor alles, dan handwerk (ook
     * zonder stempel, want gevuld doel zonder stempel is per definitie niet
     * door ons vertaald), dan een gewijzigde bron, en pas dan "vertaald".
     */
    public static function deriveStatus(bool $complete, bool $stamped, bool $targetChanged, bool $sourceChanged): string
    {
        if (! $complete) {
            return self::STATUS_MISSING;
        }

        if (! $stamped || $targetChanged) {
            return self::STATUS_MANUAL;
        }

        if ($sourceChanged) {
            return self::STATUS_OUTDATED;
        }

        return self::STATUS_TRANSLATED;
    }

    public static function labels(): array
    {
        return [
            self::STATUS_MISSING => __('Ontbreekt'),
            self::STATUS_OUTDATED => __('Verouderd'),
            self::STATUS_MANUAL => __('Handmatig aangepast'),
            self::STATUS_TRANSLATED => __('Vertaald'),
        ];
    }
}

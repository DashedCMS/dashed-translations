<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Dashed\DashedTranslations\Classes\Translatables\Translatable;
use Dashed\DashedTranslations\Classes\Translatables\TranslatableRegistry;

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
     * Alleen de soorten die op dit moment geregistreerd staan.
     *
     * Eén definitie voor het scherm, de statswidget, de bevestigingsmodal en
     * TranslateOutdatedJob, zodat het cijfer in de widget, het cijfer in de
     * modal en wat de knop daadwerkelijk oppakt over dezelfde rijen gaan.
     *
     * Bewust `whereIn` op model_type en niet `whereHasMorph('model', '*')`:
     * die laatste doet eerst een `select distinct model_type` en zet daarna
     * per soort een EXISTS-tak neer (hier veertig), op de lijstquery, de
     * telling én elke poll van vijf seconden. Hij doet bovendien `new $type`
     * per gevonden soort, dus een project dat een pakket verwijdert krijgt
     * een fatale "class not found" op het scherm. model_type is de eerste
     * kolom van de unieke index, dus deze variant is ook nog indexdekkend.
     * Rijen waarvan het record zelf weg is blijven staan; de kolommen en
     * acties vangen een lege morph-relatie al op, en de rescan ruimt ze op.
     */
    public function scopeInRegister(Builder $query): Builder
    {
        $classes = array_map(
            fn (Translatable $entry) => $entry->modelClass(),
            app(TranslatableRegistry::class)->alles()
        );

        return $query->whereIn('model_type', $classes);
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

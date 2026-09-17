<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedTranslations\Models\TranslationState;

/**
 * De enige plek die dashed__translation_states schrijft. refresh() vergelijkt
 * de huidige inhoud met de laatste stempel; stamp() zet een nieuwe stempel.
 * Alleen stamp() kan "vertaald" maken.
 *
 * Elke publieke methode ruimt aan het eind ook zijn eigen relatiecache weer
 * op: ContentFingerprint leest onderweg de kindrelaties van het model en
 * Eloquent cachet die lezing, inclusief een lege uitkomst. Zonder die tweede
 * reloadChildren()-aanroep blijft een net aangemaakt kind (bijvoorbeeld
 * metadata die pas na stamp() wordt opgeslagen) onzichtbaar voor de
 * aanroeper die hetzelfde modelobject daarna nog gebruikt.
 */
class TranslationStateUpdater
{
    public static function fromLocale(): string
    {
        return Locales::getFirstLocale()['id'];
    }

    /** @return array<int, string> */
    public static function toLocales(): array
    {
        return array_keys(Locales::getLocalesArrayWithoutCurrent(self::fromLocale()));
    }

    /**
     * Het geregistreerde model waar $model bij hoort: het model zelf, of zijn
     * ouder als het een kind is. Null als het nergens bij hoort.
     *
     * @return array{0: Model, 1: Translatable}|null
     */
    public static function resolve(Model $model): ?array
    {
        $registry = app(TranslatableRegistry::class);

        if ($registration = $registry->vind($model::class)) {
            return [$model, $registration];
        }

        $parent = $registry->ouderVan($model);

        if ($parent && ($registration = $registry->vind($parent::class))) {
            return [$parent, $registration];
        }

        return null;
    }

    public static function refresh(Model $model): void
    {
        if (! $resolved = self::resolve($model)) {
            return;
        }

        [$model, $registration] = $resolved;
        self::reloadChildren($model, $registration);
        $from = self::fromLocale();

        foreach (self::toLocales() as $to) {
            self::refreshOne($model, $registration, $from, $to);
        }

        self::reloadChildren($model, $registration);
    }

    public static function stamp(Model $model, string $fromLocale, string $toLocale): void
    {
        if (! $resolved = self::resolve($model)) {
            return;
        }

        [$model, $registration] = $resolved;
        self::reloadChildren($model, $registration);

        $state = self::row($model, $fromLocale, $toLocale);
        $complete = ContentFingerprint::isComplete($model, $fromLocale, $toLocale, $registration);

        $state->fill([
            'source_hash' => ContentFingerprint::for($model, $fromLocale, $registration),
            'target_hash' => ContentFingerprint::for($model, $toLocale, $registration),
            'source_changed' => false,
            'target_changed' => false,
            'complete' => $complete,
            'status' => TranslationState::deriveStatus($complete, true, false, false),
            'translated_at' => now(),
            'checked_at' => now(),
        ])->save();

        self::reloadChildren($model, $registration);
    }

    /**
     * Nulmeting: vergelijk, en stempel alleen wat compleet is én nog nooit
     * gestempeld. Een bestaande stempel blijft staan, anders zou een tweede
     * ronde verouderde items weer op "vertaald" zetten.
     */
    public static function baseline(Model $model): void
    {
        if (! $resolved = self::resolve($model)) {
            return;
        }

        [$model, $registration] = $resolved;
        self::reloadChildren($model, $registration);
        $from = self::fromLocale();

        foreach (self::toLocales() as $to) {
            $state = self::refreshOne($model, $registration, $from, $to);

            if ($state->complete && $state->source_hash === null) {
                self::stamp($model, $from, $to);
            }
        }

        self::reloadChildren($model, $registration);
    }

    public static function forget(Model $model): void
    {
        TranslationState::voorModel($model)->delete();
    }

    protected static function refreshOne(Model $model, Translatable $registration, string $from, string $to): TranslationState
    {
        $state = self::row($model, $from, $to);
        $stamped = $state->source_hash !== null;
        $complete = ContentFingerprint::isComplete($model, $from, $to, $registration);
        $sourceChanged = $stamped && $state->source_hash !== ContentFingerprint::for($model, $from, $registration);
        $targetChanged = $stamped && $state->target_hash !== ContentFingerprint::for($model, $to, $registration);

        $state->fill([
            'source_changed' => $sourceChanged,
            'target_changed' => $targetChanged,
            'complete' => $complete,
            'status' => TranslationState::deriveStatus($complete, $stamped, $targetChanged, $sourceChanged),
            'checked_at' => now(),
        ])->save();

        return $state;
    }

    protected static function row(Model $model, string $from, string $to): TranslationState
    {
        return TranslationState::firstOrNew([
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'from_locale' => $from,
            'to_locale' => $to,
        ]);
    }

    /**
     * Een kind dat net los is opgeslagen zit nog oud in de relatiecache van
     * de ouder; gooi die weg zodat de vingerafdruk de nieuwe inhoud ziet.
     */
    protected static function reloadChildren(Model $model, Translatable $registration): void
    {
        foreach (array_keys($registration->children()) as $relation) {
            $model->unsetRelation($relation);
        }
    }
}

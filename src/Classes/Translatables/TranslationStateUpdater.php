<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedTranslations\Models\TranslationState;
use Illuminate\Database\Eloquent\Model;

/**
 * De enige plek die dashed__translation_states schrijft. refresh() vergelijkt
 * de huidige inhoud met de laatste stempel; stamp() zet een nieuwe stempel.
 * Alleen stamp() kan "vertaald" maken.
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
        ContentFingerprint::forgetChildren($model);

        try {
            $from = self::fromLocale();

            foreach (self::toLocales() as $to) {
                self::refreshOne($model, $registration, $from, $to);
            }
        } finally {
            // Niets van deze aanroep mag blijven hangen: een lang levende
            // queue worker verwerkt hierna misschien duizenden andere
            // modellen, en die mogen dit geheugen niet zien groeien.
            ContentFingerprint::forgetChildren($model);
        }
    }

    public static function stamp(Model $model, string $fromLocale, string $toLocale): void
    {
        if (! $resolved = self::resolve($model)) {
            return;
        }

        [$model, $registration] = $resolved;
        ContentFingerprint::forgetChildren($model);

        try {
            self::stampNow($model, $registration, $fromLocale, $toLocale);
        } finally {
            ContentFingerprint::forgetChildren($model);
        }
    }

    /**
     * De kern van stamp(), zonder de memo aan te raken. stamp() zelf wikkelt
     * dit in forgetChildren() aan begin en eind, voor een aanroeper die maar
     * één stempel zet. baseline() roept dit rechtstreeks aan, meerdere keren
     * in zijn eigen lus over de doeltalen: zou het via stamp() lopen, dan
     * wist elke stempel de memo alweer voordat de volgende taal in dezelfde
     * lus zijn kinderen leest, en werd elke taal na de eerste een verse
     * query. baseline() beheert zijn eigen forgetChildren() al aan begin en
     * eind van de hele lus (zie hieronder), dus hier is geen extra staat
     * nodig die zou kunnen verouderen: de kinderen van dit model veranderen
     * niet tussen twee doeltalen binnen dezelfde baseline()-aanroep.
     */
    private static function stampNow(Model $model, Translatable $registration, string $fromLocale, string $toLocale): void
    {
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
    }

    /**
     * Stempel vanuit de vertaalpijplijn. translateModel() stuurt model,
     * metadata en customBlocks als drie losse jobs; elk stempelt de ouder.
     * Zolang er voor de ouder of een kind nog een progress-rij loopt voor
     * deze taal, stempelt de laatste; ReplaceStringsInModel wacht zelf al
     * op zijn eigen model, dit is het vangnet voor de andere twee.
     */
    public static function stampFromProgress(Model $model, string $fromLocale, string $toLocale): void
    {
        if (! $resolved = self::resolve($model)) {
            return;
        }

        [$parent, $registration] = $resolved;
        ContentFingerprint::forgetChildren($parent);

        try {
            $pending = \Dashed\DashedTranslations\Models\AutomatedTranslationProgress::query()
                ->where('to_locale', $toLocale)
                ->whereNotIn('status', ['finished', 'error'])
                ->where(function ($query) use ($parent, $registration) {
                    $query->where(fn ($q) => $q->where('model_type', $parent::class)->where('model_id', $parent->getKey()));

                    foreach (array_keys($registration->children()) as $relation) {
                        foreach (ContentFingerprint::childrenOf($parent, $relation) as $child) {
                            $query->orWhere(fn ($q) => $q->where('model_type', $child::class)->where('model_id', $child->getKey()));
                        }
                    }
                })
                ->exists();

            if ($pending) {
                self::refresh($parent);

                return;
            }

            self::stamp($parent, $fromLocale, $toLocale);
        } finally {
            ContentFingerprint::forgetChildren($parent);
        }
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
        ContentFingerprint::forgetChildren($model);

        try {
            $from = self::fromLocale();

            foreach (self::toLocales() as $to) {
                $state = self::refreshOne($model, $registration, $from, $to);

                if ($state->complete && $state->source_hash === null) {
                    self::stampNow($model, $registration, $from, $to);
                }
            }
        } finally {
            ContentFingerprint::forgetChildren($model);
        }
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
}

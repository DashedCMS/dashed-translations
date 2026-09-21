<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;

/**
 * Eén ingang voor het starten van een vertaling vanuit het
 * vertaalstatus-overzicht: het model zelf plus elk geregistreerd kind dat
 * meetelt in zijn vingerafdruk.
 *
 * AutomatedTranslation::translateModel() stuurt precies drie dingen weg: het
 * model, zijn `metadata` en zijn `customBlocks`. Drie registraties melden
 * ANDERE kinderen aan die wel in de vingerafdruk van de ouder meetellen
 * (Form->fields, ProductFilter->productFilterOptions,
 * ProductExtra->productExtraOptions). Zonder deze klasse blijft de bron van
 * die kinderen onvertaald, wordt de vingerafdruk van het doel dus nooit
 * compleet, en blijft de statusrij op `missing` staan terwijl de knop
 * "Vertaling gestart" meldt en de nachtelijke ronde het item elke nacht
 * opnieuw pakt. De bewerkpagina van een formulier deed dit al met de hand
 * (EditForm::getActions(), de translate-actie loopt daar over
 * $this->record->fields); dit is dezelfde beweging op één plek.
 *
 * De rest van de plumbing bestaat al: StartTranslationOfModel maakt per kind
 * zijn eigen progress-rij, TranslationStateUpdater::resolve() vouwt de
 * stempel van een kind terug op de ouder, en stampFromProgress() wacht op de
 * progress-rijen van de kinderen voordat hij de ouder stempelt.
 */
class TranslationStarter
{
    /**
     * De twee relaties die translateModel() zelf al meestuurt. Op relatienaam
     * en niet op klasse, want translateModel() leest ze als property
     * ($model->metadata, $model->customBlocks); dat zijn exact de twee namen
     * die Translatable::contentChildren() registreert.
     *
     * @var array<int, string>
     */
    protected const DOOR_TRANSLATE_MODEL = ['metadata', 'customBlocks'];

    /**
     * @param  array<int, string>  $toLocales
     */
    public static function start(Model $model, string $fromLocale, array $toLocales): void
    {
        AutomatedTranslation::translateModel($model, $fromLocale, $toLocales);

        $registration = app(TranslatableRegistry::class)->vind($model::class);

        if (! $registration) {
            return;
        }

        try {
            foreach ($registration->children() as $relation => $definition) {
                if (in_array($relation, self::DOOR_TRANSLATE_MODEL, true)) {
                    continue;
                }

                foreach (ContentFingerprint::childrenOf($model, $relation) as $child) {
                    AutomatedTranslation::translateModel($child, $fromLocale, $toLocales);
                }
            }
        } finally {
            // Zelfde regel als in TranslationStateUpdater: niets van deze
            // aanroep blijft in de memo hangen, zodat een langlevende worker
            // die hierna duizenden andere modellen verwerkt dit geheugen niet
            // ziet groeien en niemand op verouderde kindrijen leest.
            ContentFingerprint::forgetChildren($model);
        }
    }
}

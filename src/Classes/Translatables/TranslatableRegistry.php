<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Illuminate\Database\Eloquent\Model;

/**
 * In-memory register van alles wat in het vertaalstatus-overzicht hoort.
 * Gebonden als singleton in registeringPackage() van dashed-translations,
 * zodat elk pakket in zijn bootingPackage() in dezelfde instantie schrijft.
 */
class TranslatableRegistry
{
    /** @var array<string, Translatable> modelklasse => registratie */
    protected array $entries = [];

    public static function register(Translatable $translatable): void
    {
        app(self::class)->registreer($translatable);
    }

    public function registreer(Translatable $translatable): void
    {
        $this->entries[$translatable->modelClass()] = $translatable;
    }

    /** @return array<int, Translatable> */
    public function alles(): array
    {
        return array_values($this->entries);
    }

    public function vind(string $modelClass): ?Translatable
    {
        return $this->entries[$modelClass] ?? null;
    }

    public function isKind(string $modelClass): bool
    {
        foreach ($this->entries as $entry) {
            foreach ($entry->children() as $child) {
                if ($child['class'] === $modelClass) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * De geregistreerde ouder van een kind, of null als het model geen kind is
     * of zijn ouder niet (meer) bestaat.
     *
     * Roept de parent-closure hooguit één keer aan, op de EERSTE registratie
     * die deze kindklasse noemt, en niet per registratie die hem noemt. De
     * closure leest alleen van $child zelf (zijn eigen foreign key of
     * morph-kolommen), nooit van welke ouder-entry hem aanroept, dus elke
     * registratie met dezelfde kindklasse zou toch dezelfde ouder opleveren.
     * `contentChildren()` registreert 'metadata' en 'customBlocks' onder elk
     * model dat die traits heeft (tientallen), dus zonder deze kortsluiting
     * deed elke save van één Metadata-rij tientallen identieke find()-queries
     * in plaats van één.
     */
    public function ouderVan(Model $child): ?Model
    {
        foreach ($this->entries as $entry) {
            foreach ($entry->children() as $definition) {
                if ($definition['class'] !== $child::class) {
                    continue;
                }

                $parent = ($definition['parent'])($child);

                return $parent instanceof Model && $this->vind($parent::class) ? $parent : null;
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->entries = [];
    }

    /**
     * Hangt de listeners aan. Elke boot opnieuw, zonder statische
     * "al gedaan"-vlag: die overleeft in tests de herstart van de applicatie
     * en hangt de listener dan aan een dispatcher die niet meer bestaat.
     */
    public function boot(): void
    {
        foreach ($this->entries as $entry) {
            $class = $entry->modelClass();

            if (! class_exists($class)) {
                continue;
            }

            $class::saved(fn (Model $model) => rescue(fn () => TranslationStateUpdater::refresh($model), report: false));
            $class::deleted(function (Model $model) {
                if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                    return;
                }

                rescue(fn () => TranslationStateUpdater::forget($model), report: false);
            });

            foreach ($entry->children() as $definition) {
                $childClass = $definition['class'];

                if (class_exists($childClass)) {
                    $childClass::saved(fn (Model $child) => rescue(fn () => TranslationStateUpdater::refresh($child), report: false));

                    // Zonder dit maakt het verwijderen van bijvoorbeeld een
                    // formulierveld de ouder onzichtbaar voor de nachtronde:
                    // de statusrij van de ouder blijft op zijn oude waarde
                    // staan (vaak 'manual', want de laatste stempel dekte nog
                    // het inmiddels verwijderde veld) en wordt dus nooit meer
                    // herzien. refresh() en niet forget(): het kind zelf heeft
                    // geen eigen statusrij, alleen de ouder. En refresh() kan,
                    // net als bij het model zelf, een stempel alleen
                    // bevestigen of afbreken, nooit zelf 'translated' maken
                    // (zie TranslationStateUpdater::refresh()); na het
                    // verwijderen van een veld toont de rij dus bijvoorbeeld
                    // 'verouderd' totdat er opnieuw gestempeld wordt, in
                    // plaats van meteen 'vertaald' als het verwijderde veld
                    // toevallig de enige ontbrekende vertaling was. Dezelfde
                    // onnauwkeurigheid gold al voor de ouder zelf.
                    $childClass::deleted(fn (Model $child) => rescue(fn () => TranslationStateUpdater::refresh($child), report: false));
                }
            }
        }
    }
}

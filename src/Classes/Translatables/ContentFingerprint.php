<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Dashed\DashedTranslations\Models\AutomatedTranslationString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Vingerafdruk van de vertaalbare inhoud van een model in één taal, inclusief
 * zijn geregistreerde kinderen. Twee vingerafdrukken zijn gelijk zodra de
 * tekst gelijk is; volgorde van blokken telt, ignorable kolommen tellen niet.
 */
class ContentFingerprint
{
    /**
     * Kindrijen per (model, relatie), binnen de levensduur van één publieke
     * aanroep in TranslationStateUpdater. Zo'n aanroep leest dezelfde
     * kinderen meerdere keren (isComplete() plus twee keer for(), per
     * doeltaal), en zonder memo was dat evenzoveel keren een verse query.
     * De kinderen kunnen binnen die ene aanroep niet veranderen, dus
     * memoiseren is daar veilig; TranslationStateUpdater roept
     * forgetChildren() aan bij het begin (en het einde) van elke publieke
     * methode om te zorgen dat niets hier veroudert of blijft hangen.
     *
     * @var array<string, Collection<int, Model>> "Klasse#id#relatie" => rijen
     */
    private static array $childrenMemo = [];

    public static function for(Model $model, string $locale, Translatable $registration): string
    {
        return hash('sha256', json_encode(self::values($model, $locale, $registration), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Compleet betekent: elke kolom die in de bron tekst heeft, heeft in het
     * doel ook tekst. Een whitespace-only bron telt niet als tekst, anders
     * blokkeert één losse non-breaking space een kolom voor altijd.
     */
    public static function isComplete(Model $model, string $fromLocale, string $toLocale, Translatable $registration): bool
    {
        $source = self::values($model, $fromLocale, $registration);
        $target = self::values($model, $toLocale, $registration);

        foreach ($source as $key => $value) {
            if (self::isEmpty($value)) {
                continue;
            }

            if (self::isEmpty($target[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed> kolom => waarde; kinderen als "relatie.id.kolom"
     */
    public static function values(Model $model, string $locale, Translatable $registration): array
    {
        $values = [];

        foreach (self::columns($model) as $column) {
            $values[$column] = self::normalize($model->getTranslation($column, $locale, false));
        }

        foreach ($registration->children() as $relation => $definition) {
            foreach (self::childrenOf($model, $relation) as $child) {
                foreach (self::columns($child) as $column) {
                    $values["{$relation}.{$child->getKey()}.{$column}"] = self::normalize($child->getTranslation($column, $locale, false));
                }
            }
        }

        return $values;
    }

    /**
     * Kolommen op de kruising van `translatable` en de tabel: `$translatable`
     * is met de hand bijgehouden en kan een kolom noemen die er niet (meer)
     * is. `Form::$translatable = ['mustHaveSomethingDefined']` is zo'n geval
     * (een bestaande bug in dat model, buiten dit werk om); zonder deze
     * filter leest `getTranslation()` een niet-bestaande kolom en gooit
     * Spatie's HasTranslations een exception per vingerafdruk.
     *
     * @return array<int, string>
     */
    public static function columns(Model $model): array
    {
        $translatable = $model->translatable ?? [];

        $ignorable = method_exists(cms(), 'ignorableColumnsForTranslations')
            ? cms()->ignorableColumnsForTranslations($model)
            : (array) cms()->builder('ignorableColumnsForTranslations');

        return array_values(array_diff(array_intersect($translatable, self::existingColumns($model)), $ignorable));
    }

    /**
     * De kolommen die de tabel echt heeft, per tabel gecachet: `Schema::
     * getColumnListing()` is een schema-query, en zonder cache zou elke
     * vingerafdruk van elk model op die tabel hem opnieuw doen.
     *
     * @var array<string, array<int, string>>
     */
    private static array $tableColumnsCache = [];

    /** @return array<int, string> */
    private static function existingColumns(Model $model): array
    {
        $table = $model->getTable();

        return self::$tableColumnsCache[$table] ??= Schema::getColumnListing($table);
    }

    /**
     * Vergeet de gememoiseerde kinderen van dit model. Raakt alleen het eigen
     * geheugen van deze klasse, nooit de relatiecache van $model zelf: die
     * cache vullen of strippen was precies de oorspronkelijke bug (een
     * vingerafdruk die tijdens Model::saved al vroeg gemaakt wordt, voordat
     * er kinderen bestaan, liet anders een verouderde lege relatie achter op
     * het model-object van de aanroeper).
     */
    public static function forgetChildren(Model $model): void
    {
        $prefix = self::memoPrefix($model);

        foreach (array_keys(self::$childrenMemo) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset(self::$childrenMemo[$key]);
            }
        }
    }

    /**
     * De kindrijen van één relatie, gememoiseerd per (model, relatie) voor de
     * duur van de huidige publieke aanroep in TranslationStateUpdater. Ook
     * gebruikt door TranslationStateUpdater::stampFromProgress() zelf, zodat
     * ook die niet nog eens los queryt of de relatiecache van het model
     * aanraakt.
     *
     * @return Collection<int, Model>
     */
    public static function childrenOf(Model $model, string $relation): Collection
    {
        if (! method_exists($model, $relation)) {
            return collect();
        }

        $key = self::memoPrefix($model) . $relation;

        if (array_key_exists($key, self::$childrenMemo)) {
            return self::$childrenMemo[$key];
        }

        // getResults() en niet de magic property: die laatste cachet het
        // resultaat op $model zelf, en dat is precies de relatiecache van de
        // aanroeper die we met opzet niet aanraken.
        $related = $model->{$relation}()->getResults();

        $rows = $related instanceof Collection
            ? $related->sortBy(fn (Model $row) => $row->getKey())->values()
            : ($related instanceof Model ? collect([$related]) : collect());

        return self::$childrenMemo[$key] = $rows;
    }

    private static function memoPrefix(Model $model): string
    {
        return $model::class . '#' . $model->getKey() . '#';
    }

    private static function normalize(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        if (is_string($value)) {
            return AutomatedTranslationString::isUntranslatable($value);
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;

/**
 * Vingerafdruk van de vertaalbare inhoud van een model in één taal, inclusief
 * zijn geregistreerde kinderen. Twee vingerafdrukken zijn gelijk zodra de
 * tekst gelijk is; volgorde van blokken telt, ignorable kolommen tellen niet.
 */
class ContentFingerprint
{
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
            foreach (self::childRows($model, $relation) as $child) {
                foreach (self::columns($child) as $column) {
                    $values["{$relation}.{$child->getKey()}.{$column}"] = self::normalize($child->getTranslation($column, $locale, false));
                }
            }
        }

        return $values;
    }

    /** @return array<int, string> */
    public static function columns(Model $model): array
    {
        $translatable = $model->translatable ?? [];

        $ignorable = method_exists(cms(), 'ignorableColumnsForTranslations')
            ? cms()->ignorableColumnsForTranslations($model)
            : (array) cms()->builder('ignorableColumnsForTranslations');

        return array_values(array_diff($translatable, $ignorable));
    }

    /** @return Collection<int, Model> */
    private static function childRows(Model $model, string $relation): Collection
    {
        if (! method_exists($model, $relation)) {
            return collect();
        }

        $related = $model->{$relation};

        if ($related instanceof Collection) {
            return $related->sortBy(fn (Model $row) => $row->getKey())->values();
        }

        return $related instanceof Model ? collect([$related]) : collect();
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

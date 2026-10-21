<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Illuminate\Database\Eloquent\Model;

/**
 * Welke sleutels in een JSON-kolom niet naar de vertaler gaan. Eén definitie
 * voor de extractie (ExtractStringsToTranslate) en de compleetheidstoets
 * (ContentFingerprint), zodat "heeft deze kolom iets te vertalen" op beide
 * plekken hetzelfde antwoord geeft.
 */
class IgnorableKeys
{
    /**
     * De vaste sleutels horen bij de structuur van een builder-blok en gelden
     * overal. De lijst die een project zelf instelt geldt alleen voor kolommen
     * die builder-inhoud bevatten: daar beschermt hij sleutels als 'name' en
     * 'slug', die een blok identificeren in plaats van iets te tonen. In een
     * kolom met gewone inhoud betekent dezelfde sleutel juist wél tekst - denk
     * aan de opties van een keuzeveld, opgeslagen als [['name' => '...']] -
     * en knijpt de lijst de kolom leeg: er viel dan niets meer te vertalen.
     *
     * @return array<int, string>
     */
    public static function for(Model $model, string $column): array
    {
        $fixedKeys = ['type', 'url', 'icon', 'background'];

        if (self::columnHoldsPlainContent($model, $column)) {
            return $fixedKeys;
        }

        return array_merge($fixedKeys, (array) cms()->builder('ignorableKeysForTranslations'));
    }

    /**
     * Of het model deze kolom heeft aangemeld als gewone inhoud in plaats van
     * builder-inhoud, via:
     *
     *     cms()->builder('plainContentColumnsForTranslations', [
     *         FormField::class => ['options', 'images'],
     *     ]);
     *
     * De vergelijking gaat via instanceof, zodat een project dat een
     * Dashed-model uitbreidt de registratie van de package erft.
     */
    public static function columnHoldsPlainContent(Model $model, string $column): bool
    {
        foreach ((array) cms()->builder('plainContentColumnsForTranslations') as $class => $columns) {
            if ($model instanceof $class && in_array($column, (array) $columns, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dezelfde toets als de extractie: een sleutel is negeerbaar zodra hij een
     * van de negeerbare sleutels bevat (str()->contains(), geen gelijkheid).
     */
    public static function ignores(string $key, array $ignorable): bool
    {
        return str($key)->contains($ignorable);
    }
}

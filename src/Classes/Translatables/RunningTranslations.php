<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Dashed\DashedCore\Models\CustomBlock;
use Dashed\DashedCore\Models\Metadata;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * De lopende en gefaalde progress-rijen, één keer per request opgehaald,
 * zodat de badge "Bezig"/"Fout" in de tabel geen query per rij kost.
 *
 * AutomatedTranslation::translateModel() zet voor een pagina met
 * contentblokken tot drie losse jobs klaar (het model zelf, zijn metadata,
 * zijn customBlocks), en StartTranslationOfModel maakt de progress-rij op de
 * sleutel van dat KIND, niet van de ouder. Zonder terugrekenen zou de badge
 * op de statusrij van de ouder "Bezig"/"Fout" missen zolang alleen een kind
 * nog loopt. Het terugrekenen gebeurt via de eigen morph-kolommen van het
 * kind (dezelfde twee paren als Translatable::contentChildren() registreert),
 * niet door de ouder zelf te laden zoals TranslatableRegistry::ouderVan() dat
 * doet: dat zou een extra query per lopende kindrij kosten. Zo blijft het
 * hooguit een extra query per kindtype dat daadwerkelijk in de opgehaalde
 * progress-rijen voorkomt, in plaats van een query per tabelrij.
 */
class RunningTranslations
{
    /** @var array<class-string, string> kindklasse => morph-kolomprefix op dat kind */
    protected const CHILD_MORPH_PREFIXES = [
        Metadata::class => 'metadatable',
        CustomBlock::class => 'blockable',
    ];

    protected static ?Collection $cache = null;

    public static function for(string $modelType, int $modelId, string $toLocale): ?AutomatedTranslationProgress
    {
        self::$cache ??= self::build();

        return self::$cache->get($modelType . '|' . $modelId . '|' . $toLocale);
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** @return Collection<string, AutomatedTranslationProgress> */
    protected static function build(): Collection
    {
        $rows = AutomatedTranslationProgress::query()
            ->where('status', '!=', 'finished')
            ->get();

        $map = collect();
        $perKindtype = [];

        foreach ($rows as $row) {
            if (! array_key_exists($row->model_type, self::CHILD_MORPH_PREFIXES)) {
                self::stash($map, $row->model_type, (int) $row->model_id, $row);

                continue;
            }

            $perKindtype[$row->model_type][] = $row;
        }

        foreach ($perKindtype as $childType => $childRows) {
            self::stashChildren($map, $childType, $childRows);
        }

        return $map;
    }

    /**
     * @param  array<int, AutomatedTranslationProgress>  $childRows
     */
    protected static function stashChildren(Collection $map, string $childType, array $childRows): void
    {
        if (! class_exists($childType)) {
            return;
        }

        $prefix = self::CHILD_MORPH_PREFIXES[$childType];
        $ids = collect($childRows)->pluck('model_id')->unique()->values()->all();

        // Een query voor dit hele kindtype, niet per rij: alleen de eigen
        // morph-kolommen van het kind, de ouder zelf wordt niet geladen.
        $kinderen = $childType::query()
            ->whereIn('id', $ids)
            ->get(['id', "{$prefix}_type", "{$prefix}_id"])
            ->keyBy('id');

        foreach ($childRows as $row) {
            $kind = $kinderen->get((int) $row->model_id);
            $parentType = $kind?->{"{$prefix}_type"};
            $parentId = $kind?->{"{$prefix}_id"};

            if (! $parentType || ! $parentId) {
                continue;
            }

            self::stash($map, Relation::getMorphedModel($parentType) ?? $parentType, (int) $parentId, $row);
        }
    }

    /**
     * Een error wint altijd van een lopende status: als zowel de ouder als
     * een kind een progress-rij op dezelfde sleutel hebben, mag de badge een
     * echte fout nooit verbergen achter "Bezig".
     */
    protected static function stash(Collection $map, string $modelType, int $modelId, AutomatedTranslationProgress $row): void
    {
        $key = $modelType . '|' . $modelId . '|' . $row->to_locale;
        $existing = $map->get($key);

        if ($existing && $existing->status === 'error' && $row->status !== 'error') {
            return;
        }

        $map->put($key, $row);
    }
}

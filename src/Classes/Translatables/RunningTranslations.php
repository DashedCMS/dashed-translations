<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Illuminate\Support\Collection;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;

/**
 * De lopende en gefaalde progress-rijen, één keer per request opgehaald,
 * zodat de badge "Bezig"/"Fout" in de tabel geen query per rij kost.
 */
class RunningTranslations
{
    protected static ?Collection $cache = null;

    public static function for(string $modelType, int $modelId, string $toLocale): ?AutomatedTranslationProgress
    {
        self::$cache ??= AutomatedTranslationProgress::query()
            ->where('status', '!=', 'finished')
            ->get()
            ->keyBy(fn (AutomatedTranslationProgress $p) => $p->model_type . '|' . $p->model_id . '|' . $p->to_locale);

        return self::$cache->get($modelType . '|' . $modelId . '|' . $toLocale);
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}

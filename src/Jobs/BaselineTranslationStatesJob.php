<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedTranslations\Classes\Translatables\TranslatableRegistry;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;

/**
 * Nulmeting: loopt alle geregistreerde modellen af en vertrouwt wat al
 * vertaald is. Bestaande stempels blijven staan (zie
 * TranslationStateUpdater::baseline()).
 */
class BaselineTranslationStatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function handle(): void
    {
        foreach (app(TranslatableRegistry::class)->alles() as $entry) {
            $class = $entry->modelClass();

            if (! class_exists($class)) {
                continue;
            }

            if (! Schema::hasTable((new $class())->getTable())) {
                Log::info("Vertaalstatus-nulmeting: tabel van {$class} bestaat niet, overgeslagen.");

                continue;
            }

            $class::query()->chunkById(200, function ($models) {
                foreach ($models as $model) {
                    rescue(fn () => TranslationStateUpdater::baseline($model), report: true);
                }
            });
        }
    }
}

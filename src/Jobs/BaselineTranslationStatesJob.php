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
 *
 * Eén job per geregistreerd model in plaats van één job over het hele
 * register: zonder $modelClass waaiert handle() uit naar één dispatch per
 * klasse. Een trage of grote tabel (of een tabel die de gestelde timeout
 * overschrijdt) blokkeert zo de andere modellen niet, en een mislukte klasse
 * is met `queue:retry` los opnieuw te proberen in plaats van de hele
 * nulmeting van voren af aan te moeten doen. Voorheen: één job, timeout 3600,
 * tries 1, over het complete register.
 */
class BaselineTranslationStatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public ?string $modelClass = null)
    {
    }

    public function handle(): void
    {
        if ($this->modelClass === null) {
            foreach (app(TranslatableRegistry::class)->alles() as $entry) {
                self::dispatch($entry->modelClass());
            }

            return;
        }

        $class = $this->modelClass;

        if (! class_exists($class)) {
            return;
        }

        if (! Schema::hasTable((new $class())->getTable())) {
            Log::info("Vertaalstatus-nulmeting: tabel van {$class} bestaat niet, overgeslagen.");

            return;
        }

        $verwerkt = 0;

        $class::query()->chunkById(200, function ($models) use (&$verwerkt, $class) {
            foreach ($models as $model) {
                rescue(fn () => TranslationStateUpdater::baseline($model), report: true);
                $verwerkt++;
            }

            Log::info("Vertaalstatus-nulmeting: {$class}, {$verwerkt} rijen verwerkt tot nu toe.");
        });

        Log::info("Vertaalstatus-nulmeting klaar voor {$class}: {$verwerkt} rijen.");
    }
}

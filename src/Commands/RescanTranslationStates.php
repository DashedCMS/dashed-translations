<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Commands;

use Dashed\DashedTranslations\Classes\Translatables\TranslatableRegistry;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;
use Dashed\DashedTranslations\Jobs\BaselineTranslationStatesJob;
use Dashed\DashedTranslations\Models\TranslationState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RescanTranslationStates extends Command
{
    protected $signature = 'dashed:translation-states:rescan {--baseline : Zet daarna ook de nulmeting in de wachtrij}';

    protected $description = 'Vergelijk alle vertaalbare inhoud opnieuw met de laatste stempel en ruim verweesde rijen op';

    public function handle(): int
    {
        $registry = app(TranslatableRegistry::class);
        $classes = array_map(fn ($entry) => $entry->modelClass(), $registry->alles());
        $from = TranslationStateUpdater::fromLocale();
        $toLocales = TranslationStateUpdater::toLocales();

        $verwijderd = TranslationState::query()
            ->where(fn ($q) => $q->whereNotIn('model_type', $classes)
                ->orWhere('from_locale', '!=', $from)
                ->orWhereNotIn('to_locale', $toLocales))
            ->delete();

        $ververst = 0;

        foreach ($registry->alles() as $entry) {
            $class = $entry->modelClass();

            if (! class_exists($class)) {
                continue;
            }

            if (! Schema::hasTable((new $class())->getTable())) {
                $this->line("Tabel van {$class} bestaat niet, overgeslagen.");
                Log::info("Vertaalstatus-rescan: tabel van {$class} bestaat niet, overgeslagen.");

                continue;
            }

            // Een soft-deleted rij is nog geen verweesde rij: TranslatableRegistry::boot()
            // ruimt de statusrijen bewust pas op bij een echte force delete
            // (de isForceDeleting()-guard daar), zodat wie een item terugzet zijn stempel
            // terugkrijgt. $class::query() alleen past de SoftDeletes-scope toe en zou een
            // item in de prullenbak dus als verweesd behandelen; withTrashed() voorkomt dat.
            $bestaandeIds = method_exists($class, 'bootSoftDeletes')
                ? $class::withTrashed()->select((new $class())->getKeyName())
                : $class::query()->select((new $class())->getKeyName());

            $verwijderd += TranslationState::query()
                ->where('model_type', $class)
                ->whereNotIn('model_id', $bestaandeIds)
                ->delete();

            $class::query()->chunkById(200, function ($models) use (&$ververst) {
                foreach ($models as $model) {
                    rescue(fn () => TranslationStateUpdater::refresh($model), report: true);
                    $ververst++;
                }
            });
        }

        $this->info("{$ververst} items ververst, {$verwijderd} verweesde rijen verwijderd.");

        if ($this->option('baseline')) {
            BaselineTranslationStatesJob::dispatch();
            $this->info('Nulmeting in de wachtrij gezet.');
        }

        return self::SUCCESS;
    }
}

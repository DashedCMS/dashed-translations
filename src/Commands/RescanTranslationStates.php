<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedTranslations\Models\TranslationState;
use Dashed\DashedTranslations\Jobs\BaselineTranslationStatesJob;
use Dashed\DashedTranslations\Classes\Translatables\TranslatableRegistry;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;

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

            if (! class_exists($class) || ! Schema::hasTable((new $class())->getTable())) {
                continue;
            }

            $verwijderd += TranslationState::query()
                ->where('model_type', $class)
                ->whereNotIn('model_id', $class::query()->select((new $class())->getKeyName()))
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

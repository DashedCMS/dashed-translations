<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Commands;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;
use Dashed\DashedTranslations\Jobs\TranslateOutdatedJob;
use Illuminate\Console\Command;

class TranslateOutdated extends Command
{
    protected $signature = 'dashed:translate-outdated';

    protected $description = 'Vertaal ontbrekende en verouderde inhoud automatisch (nachtelijke ronde, alleen als de instelling aanstaat)';

    public function handle(): int
    {
        if (! Customsetting::get('auto_translate_outdated')) {
            $this->info('Automatisch bijwerken staat uit.');

            return self::SUCCESS;
        }

        if (! AutomatedTranslation::automatedTranslationsEnabled()) {
            $this->warn('Geen vertaalprovider actief.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) Customsetting::get('auto_translate_max_per_run', null, '200'));

        TranslateOutdatedJob::dispatch(TranslationStateUpdater::toLocales(), false, $limit);
        $this->info("Vertaalronde in de wachtrij gezet (maximaal {$limit} items).");

        return self::SUCCESS;
    }
}

<?php

namespace Dashed\DashedTranslations\Commands;

use Illuminate\Console\Command;
use Dashed\DashedTranslations\Jobs\TranslateString;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;

class RepairTranslationStrings extends Command
{
    protected $signature = 'dashed:repair-translation-strings
        {--redispatch : Re-queue a TranslateString job for every untranslated string after repairing}
        {--dry-run : Only report what would change, write nothing}';

    protected $description = 'Repair poisoned automated-translation strings (translated=1 but empty to_string) that caused targets to show untranslated source text';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Strings flagged as translated while their translation is actually
        // empty. These pushed progress to a bogus 100% / "finished" and made
        // the replace step fall back to the source text. Resetting them lets
        // the (fixed) pipeline translate them for real on the next run.
        $poisonedQuery = AutomatedTranslationString::where('translated', 1)
            ->where(function ($q) {
                $q->whereNull('to_string')->orWhere('to_string', '');
            });

        $poisoned = (clone $poisonedQuery)->count();
        $untranslated = AutomatedTranslationString::where('translated', 0)->count();

        $this->info(sprintf('Poisoned strings (translated=1, empty to_string): %d', $poisoned));
        $this->info(sprintf('Untranslated strings (translated=0): %d', $untranslated));

        if ($dryRun) {
            $this->warn('Dry run - no changes written.');

            return self::SUCCESS;
        }

        if ($poisoned > 0) {
            (clone $poisonedQuery)->update(['translated' => 0]);
            $this->info(sprintf('Reset %d poisoned string(s) to untranslated.', $poisoned));
        }

        if ($this->option('redispatch')) {
            $count = 0;
            AutomatedTranslationString::where('translated', 0)
                ->chunkById(200, function ($strings) use (&$count) {
                    foreach ($strings as $string) {
                        TranslateString::dispatch($string);
                        $count++;
                    }
                });
            $this->info(sprintf('Re-queued %d TranslateString job(s).', $count));
        }

        $this->info('Done. Re-run the translation for affected records (admin "Vertaal" or translateModel) to rebuild the target content from source.');

        return self::SUCCESS;
    }
}

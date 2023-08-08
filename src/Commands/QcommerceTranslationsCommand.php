<?php

namespace Dashed\DashedTranslations\Commands;

use Illuminate\Console\Command;

class DashedTranslationsCommand extends Command
{
    public $signature = 'dashed-translations';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

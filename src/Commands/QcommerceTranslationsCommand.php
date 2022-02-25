<?php

namespace Qubiqx\QcommerceTranslations\Commands;

use Illuminate\Console\Command;

class QcommerceTranslationsCommand extends Command
{
    public $signature = 'qcommerce-translations';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

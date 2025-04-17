<?php

namespace Dashed\DashedTranslations\Commands;

use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RemoveUnusedTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:remove-unused-translations';

    protected $aliases = [
        'dashed:delete-unused-translations',
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unused translations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        foreach (Translation::all() as $translation) {
            $hasValue = false;
            foreach (json_decode($translation->getRawOriginal('value') ?: '{}', true) as $value) {
                if ($value) {
                    $hasValue = true;

                    break;
                }
            }

            if (! $hasValue) {
                $translation->delete();
            }
        }

        Artisan::call('cache:clear');

        return 0;
    }
}

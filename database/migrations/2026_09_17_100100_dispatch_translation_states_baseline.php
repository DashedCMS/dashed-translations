<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Dashed\DashedTranslations\Jobs\BaselineTranslationStatesJob;

/**
 * Zet de nulmeting in de wachtrij. De migratie is meteen klaar; de wachtrij
 * werkt de items in porties af. Niet tijdens tests: de suite draait
 * migrate:fresh per bestand met de wachtrij op sync.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        BaselineTranslationStatesJob::dispatch();
    }

    public function down(): void
    {
    }
};

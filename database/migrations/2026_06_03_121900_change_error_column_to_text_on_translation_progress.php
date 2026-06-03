<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * The `error` column was a VARCHAR(255), but provider error messages
     * (e.g. DeepL HTTP 403 responses) are longer than that, causing the job
     * to crash with "Data too long for column 'error'" while trying to record
     * the original failure. Widen it to TEXT so errors are stored, not thrown.
     */
    public function up(): void
    {
        Schema::table('dashed__automated_translation_progress', function (Blueprint $table) {
            $table->text('error')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashed__automated_translation_progress', function (Blueprint $table) {
            $table->string('error')
                ->nullable()
                ->change();
        });
    }
};

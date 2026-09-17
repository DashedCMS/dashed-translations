<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__translation_states', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('from_locale', 10);
            $table->string('to_locale', 10);
            $table->string('source_hash', 64)->nullable();
            $table->string('target_hash', 64)->nullable();
            $table->boolean('source_changed')->default(false);
            $table->boolean('target_changed')->default(false);
            $table->boolean('complete')->default(false);
            $table->string('status', 20)->default('missing');
            $table->timestamp('translated_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'from_locale', 'to_locale'], 'translation_states_model_locales_unique');
            $table->index(['status', 'to_locale'], 'translation_states_status_locale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__translation_states');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__automated_translation_progress', function (Blueprint $table) {
            $table->id();

            $table->string('model_type');
            $table->integer('model_id');
            $table->integer('total_columns_to_translate')
                ->default(0);
            $table->integer('total_columns_translated')
                ->default(0);
            $table->string('from_locale');
            $table->string('to_locale');
            $table->string('status')
            ->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

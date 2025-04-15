<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__automated_translation_strings', function (Blueprint $table) {
            $table->id();

            $table->string('from_locale');
            $table->string('to_locale');
            $table->text('from_string');
            $table->text('to_string')
                ->nullable();
            $table->boolean('translated')
                ->default(false);

            $table->timestamps();
        });

        Schema::create('dashed__automated_translation_progress_string', function (Blueprint $table) {
            $table->id();

             $table->unsignedBigInteger('automated_translation_progress_id');
             $table->foreign('automated_translation_progress_id', 'fk_progress_id')
                 ->references('id')->on('dashed__automated_translation_progress')
                 ->onDelete('cascade');

             $table->unsignedBigInteger('automated_translation_string_id');
             $table->foreign('automated_translation_string_id', 'fk_string_id')
                 ->references('id')->on('dashed__automated_translation_strings')
                 ->onDelete('cascade');

             $table->boolean('replaced')
             ->default(false);
             $table->string('column');
        });

        Schema::table('dashed__automated_translation_progress', function (Blueprint $table) {
            $table->renameColumn('total_columns_to_translate', 'total_strings_to_translate');
            $table->renameColumn('total_columns_translated', 'total_strings_translated');
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

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTranslations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__translations', function (Blueprint $table) {
            $table->increments('id');

            $table->string('tag')->default('general');
            $table->longText('name');
            $table->longText('default')->nullable();
            $table->json('value')->nullable();
            $table->string('type')->default('text');
            $table->string('variables')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

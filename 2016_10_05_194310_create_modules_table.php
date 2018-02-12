<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Every core and premium module should be listed here.
     * Core modules need listing for the relevant price_plan limitations
     *
     * @return void
     */
    public function up()
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name_id'); // ->unique(); // todo, readd, was breaking seeder
            $table->string('name'); // ->unique(); // todo, readd, was breaking seeder
            $table->string('brief')->nullable();
            $table->longText('description');
            $table->string('image')->nullable();

            $table->enum('type', ['per-service','per-branch','per-user'])->default('per-service');
            $table->boolean('allow_offers')->default(1);
            $table->boolean('allow_trial')->default(1);
            $table->boolean('in_development')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('modules');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModuleLimitationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * There can be many rows for each module_id
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_limitations', function (Blueprint $table) {
            $table->increments('id');
            
            $table->integer('moduleable_id');
            $table->string('moduleable_type_id');
            $table->integer('price_plan_id')->nullable(); // could be a module_core
            $table->string('json')->nullable();

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
        Schema::dropIfExists('module_limitations');
    }
}

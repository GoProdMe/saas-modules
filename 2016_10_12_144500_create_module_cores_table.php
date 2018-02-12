<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModuleCoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * This table exists so that module limitations can be defined
     * Only core modules with limitation need adding
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_cores', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name_id'); // ->unique(); // todo, readd, was breaking seeder
            $table->string('name'); // ->unique(); // todo, readd, was breaking seeder
            $table->string('brief')->nullable();

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
        Schema::dropIfExists('module_cores');
    }
}

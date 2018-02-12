<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModuleCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_categories', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name');
            $table->string('description')->nullable();

            $table->integer('parent_id')->unsigned()->default(0);

            $table->integer('index')->default(0);
            $table->integer('group')->default(0);
            $table->integer('status')->default(1);

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
        Schema::dropIfExists('module_categories');
    }
}

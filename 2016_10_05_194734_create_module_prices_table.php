<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModulePricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Just record the relevant entries. Don't store 0 unless selling free items.
     * Eg one-off: 0 AND life-time: 0 doesn't make sense.
     * Core Modules shouldn't be added here.
     * Each module should have prices for the 1st price plan.
     * Subsequent prices are optional. Logically, subsequent prices would apply to current live price
     * plan.  No need to fill in the middle ones as it will inherit from 1st
     *
     * A setup fee prevents users canceling then re-adding
     *
     * If no premium price, inherit from free
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_prices', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('module_id');
            $table->integer('price_plan_id');

            $table->float('price', 7,2)->default(0);
            $table->enum('price_frequency', ['setup','one-off','monthly','annually','lifetime'])->default('monthly');

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
        Schema::dropIfExists('module_prices');
    }
}

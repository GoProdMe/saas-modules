<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePricePlanServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_plan_service', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('price_plan_id');
            $table->integer('service_id');
            $table->date('start');
            $table->date('end')->nullable();
            $table->boolean('active')->default(0);

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
        Schema::dropIfExists('price_plan_service');
    }
}

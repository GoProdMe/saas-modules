<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentScheduleItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_schedule_items', function (Blueprint $table) {
            $table->increments('id');
            
            $table->integer('payment_schedule_id')->unsigned();
            $table->integer('module_service_id')->unsigned();

            $table->float('price_net', 7,2);
            $table->float('price_vat', 6,2);
            $table->float('price_gross', 8,2);

            $table->boolean('pro_rata')->default(0);
            $table->boolean('recurring')->default(1);

            $table->string('invoice_row_description');

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
        Schema::dropIfExists('payment_schedule_items');
    }
}

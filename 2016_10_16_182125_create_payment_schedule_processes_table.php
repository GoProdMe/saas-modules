<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentScheduleProcessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_schedule_processes', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('payment_schedule_id')->unsigned();
            $table->dateTime('date_time_run');
            $table->integer('payment_methods_service_id')->unsigned();
            
            $table->string('response');
            $table->enum('status',['success','fail']);
            $table->string('notes');
            
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
        Schema::dropIfExists('payment_schedule_processes');
    }
}

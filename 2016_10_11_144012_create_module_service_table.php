<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateModuleServiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * The renewal date is from 1st of following month - IF ANNUALLY
     *
     * The historic prices are not store here. This enables prices to be increased more easily.
     * The module prices cannot be edit once modules are in use.
     * Saas owners deal with price increases manually.
     * The invoices will keep a history/log.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_service', function (Blueprint $table) {
            $table->increments('id');

            // The current module price_plan which the user is signing up to.
            // This isn't explicit so needs to be worked out, and therefore stored.
            // If there isn't a price_plan_id for current_plan, the nearest previous price is used
            $table->integer('price_plan_id');

            $table->integer('module_id');
            $table->integer('service_id');

            $table->dateTime('order_date');
            $table->integer('ordered_by_user');
            $table->dateTime('approved_date')->nullable();
            $table->integer('approved_by_user')->nullable();
            $table->enum('approved_method', ['owner','implicit'])->nullable();

            $table->date('start_date')->nullable(); // The date the functionality and billing (inc pro rata) start
            //$table->date('contract_end_date')->nullable(); // TBC - probably will be calculated on the fly, else will need updating each month/year
            $table->date('module_expiry_date')->nullable(); // the date the module stops working

            $table->enum('contract_period',['setup','one-off','monthly','annually','lifetime']);

            $table->dateTime('cancel_date')->nullable(); // the date cancelled
            $table->integer('cancelled_by_user')->nullable();
            $table->dateTime('cancel_approved_date')->nullable();
            $table->integer('cancel_approved_by_user')->nullable();
            $table->enum('cancel_method', ['owner','implicit'])->nullable();

            $table->enum('status',['pending-activation','active','pending-cancellation','cancelled']);

            $table->string('notes')->nullable();

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
        Schema::dropIfExists('module_service');
    }
}

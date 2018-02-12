<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupportThreadTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('support_thread', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('support_id');
            $table->dateTime('date');
            $table->enum('user',['client','admin']);
            $table->longText('message');
            $table->dateTime('userRead')->nullable(); // this is set when the reporting 'user' reads, not Sass Admin, Service Admin, or another Service User

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
        Schema::dropIfExists('support_thread');
    }
}

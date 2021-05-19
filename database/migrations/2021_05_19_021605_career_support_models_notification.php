<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CareerSupportModelsNotification extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('career_support_models_notification', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('school_id')->unsigned();
            $table->bigInteger('student_id')->unsigned();

            $table->string('message');
            $table->timestamp('date');
            $table->timestamp('time');


            $table->foreign('school_id')->references('id')->on('career_support_models_school');
            $table->foreign('student_id')->references('id')->on('career_support_models_student');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('career_support_models_notification');
    }
}

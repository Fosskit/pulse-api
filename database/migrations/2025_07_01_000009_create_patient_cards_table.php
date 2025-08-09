<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('patient_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('card_id')->index();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->commonFields();

            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_cards');
    }
};

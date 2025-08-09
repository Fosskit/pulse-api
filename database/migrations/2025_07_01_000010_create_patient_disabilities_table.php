<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('patient_disabilities', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('disability_id')->index();

            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_disabilities');
    }
};

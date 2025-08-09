<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 77)->nullable()->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('health_facility_id')->index();
            $table->unsignedBigInteger('visit_type_id')->nullable()->index();
            $table->unsignedBigInteger('admission_type_id')->index();
            $table->datetime('admitted_at')->index();
            $table->datetime('discharged_at')->nullable()->index();
            $table->unsignedBigInteger('discharge_type_id')->nullable()->index();
            $table->unsignedBigInteger('visit_outcome_id')->nullable()->index();
            $table->commonFields();



            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visits');
    }
};

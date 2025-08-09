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
            $table->ulid()->index();
            $table->foreignId('patient_id')->index();
            $table->foreignId('facility_id')->index()->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('visit_type_id')->nullable()->index()->constrained('terms')->cascadeOnDelete();
            $table->foreignId('admission_type_id')->index()->constrained('terms')->cascadeOnDelete();
            $table->datetime('admitted_at')->index();
            $table->datetime('discharged_at')->nullable()->index();
            $table->foreignId('discharge_type_id')->nullable()->index()->constrained('terms')->cascadeOnDelete();
            $table->foreignId('visit_outcome_id')->nullable()->index()->constrained('terms')->cascadeOnDelete();
            $table->commonFields();



            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visits');
    }
};

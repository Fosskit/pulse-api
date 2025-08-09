<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('procedures');

        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('patient_id')->index()->constrained('patients');
            $table->unsignedBigInteger('encounter_id')->index()->constrained('encounters');
            $table->foreignId('procedure_concept_id')->index()->constrained('concepts');
            $table->foreignId('outcome_id')->index()->constrained('concepts');
            $table->foreignId('body_site_id')->index()->constrained('concepts');
            $table->dateTime('performed_at')->nullable();
            $table->dateTime('performed_by')->nullable();
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('procedures');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('encounter_type_id')->nullable()->index()->comment('Admission, Discharge, Transfer');
            $table->boolean('is_new')->default(0)->index()->comment('Old Case, New Case');
            $table->unsignedBigInteger('encounter_form_id')->index()->comment('Triage, OPD, MPH, ...');
            $table->datetime('started_at')->useCurrent()->index();
            $table->datetime('ended_at')->nullable()->index();
            $table->commonFields();



            $table->foreign('visit_id')->references('id')->on('visits');
        });
    }

    public function down()
    {
        Schema::dropIfExists('encounters');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->bigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('encounter_id')->nullable()->index();
            $table->string('code', 77)->nullable()->index();
            $table->unsignedBigInteger('observation_status_id')->index();
            $table->unsignedBigInteger('concept_id')->index();
            $table->unsignedBigInteger('body_site_id')->nullable()->index();
            $table->string('value_string', 190)->nullable();
            $table->float('value_number')->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_complex')->nullable();
            $table->datetime('value_datetime')->nullable();
            $table->datetime('observed_at')->useCurrent();
            $table->bigInteger('observed_by')->nullable();
            $table->commonFields();



            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('encounter_id')->references('id')->on('encounters');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conditions');
    }
};

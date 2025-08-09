<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('medication_requests');

        Schema::create('medication_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 77)->nullable()->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('status_id')->index();
            $table->unsignedBigInteger('intent_id')->index();
            $table->unsignedBigInteger('medication_id')->index();
            $table->unsignedBigInteger('requester_id')->index();
            $table->unsignedBigInteger('quantity')->index();
            $table->unsignedBigInteger('medication_instruction_id')->index();
            $table->unsignedBigInteger('measurement_unit_id')->index();
            $table->commonFields();

            $table->foreign('visit_id')->references('id')->on('visits');
            $table->foreign('medication_instruction_id')->references('id')->on('medication_instructions');
        });
    }

    public function down()
    {
        Schema::dropIfExists('medication_requests');
    }
};

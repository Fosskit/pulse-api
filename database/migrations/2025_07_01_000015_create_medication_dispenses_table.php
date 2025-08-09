<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('medication_dispenses');

        Schema::create('medication_dispenses', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('visit_id')->index()->constrained('visits');
            $table->unsignedBigInteger('status_id')->index();
            $table->foreignId('medication_request_id')->index()->constrained('medication_requests');
            $table->unsignedBigInteger('dispenser_id')->index();
            $table->unsignedBigInteger('quantity')->index();
            $table->unsignedBigInteger('unit_id')->index();
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('medication_requests');
    }
};

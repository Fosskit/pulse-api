<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('service_requests');

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('visit_id')->index()->constrained('visits');
            $table->unsignedBigInteger('status_id')->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->enum('request_type', ['Imaging', 'Laboratory', 'Procedure'])->nullable()->index();
            $table->foreignId('encounter_id')->index()->constrained('encounters');
            $table->dateTime('ordered_at')->index();
            $table->dateTime('completed_at')->index();
            $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('scheduled_for')->nullable();
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_requests');
    }
};

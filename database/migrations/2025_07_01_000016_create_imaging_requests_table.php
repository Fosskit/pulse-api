<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('imaging_requests');

        Schema::create('imaging_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('service_request_id')->index()->constrained('service_requests');
            $table->foreignId('modality_concept_id')->index()->constrained('concepts');
            $table->foreignId('body_site_concept_id')->index()->constrained('concepts');
            $table->text('reason_for_study')->nullable();
            $table->dateTime('performed_at')->nullable();
            $table->dateTime('performed_by')->nullable();
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('imaging_requests');
    }
};

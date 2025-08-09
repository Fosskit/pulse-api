<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('laboratory_requests');

        Schema::create('laboratory_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('service_request_id')->index()->constrained('service_requests');
            $table->foreignId('test_concept_id')->index()->constrained('concepts');
            $table->foreignId('specimen_type_concept_id')->index()->constrained('concepts');
            $table->text('reason_for_study')->nullable();
            $table->dateTime('performed_at')->nullable();
            $table->dateTime('performed_by')->nullable();
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('laboratory_requests');
    }
};

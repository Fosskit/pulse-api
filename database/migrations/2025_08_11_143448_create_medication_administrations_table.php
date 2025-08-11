<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('medication_request_id')->index();
            $table->unsignedBigInteger('status_id')->index();
            $table->unsignedBigInteger('administrator_id')->index();
            $table->decimal('dose_given', 8, 2);
            $table->unsignedBigInteger('dose_unit_id')->index();
            $table->timestamp('administered_at');
            $table->text('notes')->nullable();
            $table->json('vital_signs_before')->nullable();
            $table->json('vital_signs_after')->nullable();
            $table->text('adverse_reactions')->nullable();
            $table->commonFields();

            $table->foreign('visit_id')->references('id')->on('visits');
            $table->foreign('medication_request_id')->references('id')->on('medication_requests');
            $table->foreign('administrator_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};

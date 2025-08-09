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
        Schema::create('patient_identities', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->string('code');
            $table->unsignedBigInteger('patient_id')->index();
            $table->foreignId('card_id')->index()->constrained('cards')->cascadeOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->json('detail');
            $table->commonFields();
            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_identities');
    }
};

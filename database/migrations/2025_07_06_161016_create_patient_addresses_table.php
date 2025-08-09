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
        Schema::create('patient_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')
                ->references('id')->on('patients')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')
                ->references('id')->on('gazetteers')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')
                ->references('id')->on('gazetteers')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('commune_id')
                ->references('id')->on('gazetteers')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('village_id')
                ->references('id')->on('gazetteers')
                ->constrained()->cascadeOnDelete();
            $table->string('street_address');
            $table->boolean('is_current')->default(true);
            $table->commonFields();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_addresses');
    }
};

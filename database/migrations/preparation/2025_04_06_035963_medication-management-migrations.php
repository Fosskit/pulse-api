<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Medications
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('generic_name')->nullable()->index();
            $table->string('form', 50)->nullable()->comment('tablet, liquid, injection, etc.');
            $table->string('strength', 100)->nullable();
            $table->string('code', 50)->nullable()->index()->comment('NDC or other codes');
            $table->string('category', 50)->nullable()->index()->comment('antibiotic, analgesic, etc.');
            $table->text('instructions')->nullable();
            $table->text('contraindications')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Medication Inventory
        Schema::create('medication_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->index()->constrained()->cascadeOnDelete();
            $table->string('batch_number', 100)->nullable()->index();
            $table->date('expiration_date')->nullable()->index();
            $table->integer('quantity_available')->default(0);
            $table->integer('reorder_level')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->dateTime('last_updated')->nullable();
            $table->timestamps();
        });

        // Prescriptions
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->dateTime('prescribed_date')->index();
            $table->string('status', 50)->index()->comment('active, completed, discontinued');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Prescription Items
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->index()->constrained()->cascadeOnDelete();
            $table->string('dosage', 100)->nullable()->comment('Simple dosage (when uniform)');
            $table->json('dosage_schedule')->nullable()->comment('For variable dosing (e.g., {"morning": "10", "evening": "20"})');
            $table->string('frequency', 100)->nullable()->comment('For simple frequency (BID, TID, QID)');
            $table->json('frequency_schedule')->nullable()->comment('For complex timing');
            $table->string('duration', 100);
            $table->integer('quantity');
            $table->integer('refills')->default(0);
            $table->string('route', 50)->nullable()->comment('oral, intravenous, etc.');
            $table->text('instructions')->nullable()->comment('Free-text instructions');
            $table->text('sig_code')->nullable()->comment('Standard formatted sig notation');
            $table->string('status', 50)->default('active')->index();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // Medication Dispenses
        Schema::create('medication_dispenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_item_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('dispenser_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->dateTime('dispense_date')->index();
            $table->integer('quantity_dispensed');
            $table->string('batch_number', 100)->nullable();
            $table->foreignId('inventory_id')->nullable()->index()->constrained('medication_inventory')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_dispenses');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medication_inventory');
        Schema::dropIfExists('medications');
    }
};

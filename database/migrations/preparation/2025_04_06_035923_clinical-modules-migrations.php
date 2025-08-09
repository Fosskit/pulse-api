<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Visits
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->string('visit_number', 50)->nullable()->unique()->index();
            $table->foreignId('facility_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('visit_type', 50)->index()->comment('OPD, IPD, ER, ANC, PNC, etc.');
            $table->dateTime('visit_date')->index();
            $table->text('chief_complaint')->nullable();
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('status', 50)->index()->comment('registered, triaged, in-progress, completed, etc.');
            $table->text('visit_reason')->nullable();
            $table->dateTime('discharge_date')->nullable();
            $table->string('discharge_disposition', 100)->nullable()->comment('home, referred, DAMA, deceased');
            $table->string('reporting_category', 50)->nullable()->index()->comment('For HC1/HO2 reporting');
            $table->string('payment_method', 50)->nullable()->comment('cash, insurance, HEF, etc.');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Appointments
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->index()->constrained()->cascadeOnDelete();
            $table->dateTime('appointment_date')->index();
            $table->integer('duration')->default(30)->comment('in minutes');
            $table->string('appointment_type', 50)->comment('check-up, follow-up, procedure');
            $table->string('status', 50)->index()->comment('scheduled, completed, cancelled, etc.');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Encounters
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->index()->constrained()->cascadeOnDelete();
            $table->string('encounter_type', 50)->index()->comment('triage, consultation, procedure, etc.');
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->dateTime('encounter_date')->index();
            $table->text('notes')->nullable();
            $table->string('status', 50)->index()->comment('in-progress, completed, etc.');
            $table->integer('duration')->nullable()->comment('in minutes');
            $table->string('reporting_category', 50)->nullable()->index()->comment('For HC1/HO2 reporting');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Triage Details
        Schema::create('triage_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('triage_level', 50)->index()->comment('emergency, urgent, semi-urgent, non-urgent');
            $table->integer('triage_score')->nullable();
            $table->text('triage_notes')->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('providers')->nullOnDelete();
            $table->timestamps();
        });

        // Diagnoses
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->index()->constrained()->cascadeOnDelete();
            $table->string('icd_code', 50)->nullable()->index();
            $table->string('diagnosis_text');
            $table->string('diagnosis_type', 50)->index()->comment('primary, secondary, etc.');
            $table->string('certainty', 50)->nullable()->comment('confirmed, provisional, differential');
            $table->date('onset_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Procedures
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->index()->constrained()->cascadeOnDelete();
            $table->string('procedure_code', 50)->nullable()->index();
            $table->string('procedure_name');
            $table->dateTime('procedure_date')->index();
            $table->text('notes')->nullable();
            $table->text('result')->nullable();
            $table->string('status', 50)->index()->comment('ordered, completed, cancelled');
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Observation Concepts
        Schema::create('observation_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('code_system', 50)->comment('LOINC, SNOMED CT, etc.');
            $table->string('display_name')->index();
            $table->string('category', 50)->index()->comment('vital-sign, lab, exam, etc.');
            $table->string('data_type', 50)->index()->comment('numeric, text, coded, etc.');
            $table->string('default_unit', 50)->nullable();
            $table->decimal('normal_range_low', 10, 2)->nullable();
            $table->decimal('normal_range_high', 10, 2)->nullable();
            $table->boolean('is_common')->index()->default(false);
            $table->timestamps();
            
            $table->unique(['code', 'code_system']);
        });

        // Observations
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->index()->constrained('observation_concepts')->cascadeOnDelete();
            $table->string('category', 50)->index()->comment('vital-sign, lab, exam, etc.');
            $table->decimal('value_numeric', 20, 10)->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->string('unit', 50)->nullable();
            $table->json('reference_range')->nullable();
            $table->string('status', 50)->index()->comment('preliminary, final, etc.');
            $table->dateTime('recorded_at')->index();
            $table->foreignId('provider_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
        Schema::dropIfExists('observation_concepts');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('triage_details');
        Schema::dropIfExists('encounters');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('visits');
    }
};

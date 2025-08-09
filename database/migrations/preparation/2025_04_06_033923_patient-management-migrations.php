<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // Geographical hierarchy
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('code', 50)->index();
            $table->string('name');
            $table->string('name_kh')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('code', 50)->index();
            $table->string('province_id', 10)->index();
            $table->string('name');
            $table->string('name_kh')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('province_id')->references('id')->on('provinces');
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('code', 50)->index();
            $table->string('district_id', 10)->index();
            $table->string('name');
            $table->string('name_kh')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('district_id')->references('id')->on('districts');
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('code', 50)->index();
            $table->string('commune_id', 10)->index();
            $table->string('name');
            $table->string('name_kh')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('commune_id')->references('id')->on('communes');
        });

        // Patients
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->index()->constrained()->cascadeOnDelete();
            $table->string('mrn', 50)->nullable()->unique()->index()->comment('Medical Record Number');
            $table->string('national_id', 100)->nullable()->index()->comment('Cambodian National ID');
            $table->string('family_name')->index();
            $table->string('given_name')->index();
            $table->date('birthdate')->nullable()->index();
            $table->enum('sex', ['F', 'M']);
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->text('address')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('occupation_id')->nullable()->index();
            $table->unsignedInteger('marital_status_id')->nullable()->index();
            $table->text('emergency_contact')->nullable();
            $table->string('photo_path', 2048)->nullable();
            $table->date('registration_date');
            $table->unsignedInteger('province_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();
            $table->unsignedInteger('commune_id')->nullable()->index();
            $table->unsignedInteger('village_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('occupation_id')->references('id')->on('lookups')->nullOnDelete();
            $table->foreign('marital_status_id')->references('id')->on('lookups')->nullOnDelete();
            $table->foreign('province_id')->references('id')->on('provinces')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
            $table->foreign('commune_id')->references('id')->on('communes')->nullOnDelete();
            $table->foreign('village_id')->references('id')->on('villages')->nullOnDelete();
        });

        // Patient Histories
        Schema::create('patient_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_name', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action_id')->index()->comment('lookup category: history_action_type');
            $table->text('change_reason')->nullable();
            $table->timestamp('created_at')->index()->nullable();
        });

        // Patient Relationships
        Schema::create('patient_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('related_patient_id')->index();
            $table->string('relationship_id')->index()->comment('lookup category: relationship');
            $table->text('notes')->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamps();

            $table->unique(['patient_id', 'related_patient_id', 'relationship_id']);
            $table->foreign('related_patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('relationship_id')->references('id')->on('lookups')->cascadeOnDelete();
        });

        // Patient Allergies
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->string('allergen');
            $table->string('allergen_type_code')->index()->comment('lookup category: allergen_type');
            $table->text('reaction')->nullable();
            $table->string('severity_code')->nullable()->index()->comment('lookup category: severity');
            $table->date('date_identified')->nullable();
            $table->boolean('is_active')->index()->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('providers')->nullOnDelete();
            $table->timestamps();
        });

        // Patient Chronic Conditions
        Schema::create('patient_chronic_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->string('condition_name');
            $table->date('diagnosis_date')->nullable();
            $table->string('status_code')->index()->comment('lookup category: condition_status');
            $table->string('icd_code', 50)->nullable()->index()->comment('International Classification of Diseases code');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('providers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_chronic_conditions');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patient_relationships');
        Schema::dropIfExists('patient_histories');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('occupations');
        Schema::dropIfExists('lookups');
    }
};

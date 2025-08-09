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
        Schema::create('clinical_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->index();
            $table->json('fhir_observation_category')->nullable();
            $table->json('form_schema'); // Dynamic form structure
            $table->json('fhir_mapping'); // Field to FHIR mapping
            $table->boolean('active')->default(true)->index();
            $table->commonFields();
        });

        // Add foreign key to encounters table
        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('clinical_form_template_id')
                ->nullable()
                ->after('encounter_form_id')
                ->constrained('clinical_form_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropForeign(['clinical_form_template_id']);
            $table->dropColumn('clinical_form_template_id');
        });

        Schema::dropIfExists('clinical_form_templates');
    }
};

<?php

// Core System Tables - Authentication & Users

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facilities
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->comment('Health center code for reporting');
            $table->string('name');
            $table->string('type', 50)->index()->comment('hospital, clinic, health center');
            $table->text('address')->nullable();
            $table->text('contact_info')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('province', 10)->index()->nullable();
            $table->string('district', 10)->index()->nullable();
            $table->string('commune', 10)->nullable();
            $table->string('village', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

        });

        // Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index()->nullable();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('category')->nullable();
            $table->enum('cpa_level', [1, 2, 3]);
            $table->decimal('default_price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();

        });

        // Providers
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('title', 50)->nullable();
            $table->string('full_name');
            $table->string('license_number', 100)->nullable();
            $table->string('specialization', 100)->index()->nullable();
            $table->text('contact_info')->nullable();
            $table->foreignId('department_id')->index()->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('services');
        Schema::dropIfExists('facilities');
    }
};

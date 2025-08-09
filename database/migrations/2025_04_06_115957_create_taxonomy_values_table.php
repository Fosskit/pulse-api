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
        // TaxonomyValue Values Table
        Schema::create('taxonomy_values', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index();
            $table->unsignedBigInteger('taxonomy_id')->index();
            $table->string('name');
            $table->string('name_kh')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['taxonomy_id', 'code']);
            $table->foreign('taxonomy_id')->references('id')->on('taxonomy_terms')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('taxonomy_values')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomy_values');
    }
};

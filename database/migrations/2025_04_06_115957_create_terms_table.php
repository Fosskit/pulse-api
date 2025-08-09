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
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index();
            $table->unsignedBigInteger('terminology_id')->index();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['terminology_id', 'code']);
            $table->foreign('terminology_id')->references('id')->on('terminologies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('terms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};

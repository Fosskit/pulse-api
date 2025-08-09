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
        Schema::create('gazetteers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index();
            $table->enum('type', ['Province', 'District', 'Commune', 'Village'])->index();
            $table->foreignId('parent_id')->index();
            $table->string('name');
            $table->commonFields();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gazetteers');
    }
};

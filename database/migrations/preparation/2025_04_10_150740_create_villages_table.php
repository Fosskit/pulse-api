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
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index();
            $table->foreignId('commune_id')->index();
            $table->string('name');
            $table->string('name_kh')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('commune_id')->references('id')->on('communes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('patient_demographics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->json('name');
            $table->date('birthdate')->index();
            $table->json('telecom')->nullable();
            $table->json('address')->nullable();
            $table->enum('sex', ['Female', 'Male'])->index();
            $table->unsignedBigInteger('nationality_id')->index();
            $table->string('telephone', 20)->nullable()->index();
            $table->datetime('died_at')->index();
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('patients');
    }
};

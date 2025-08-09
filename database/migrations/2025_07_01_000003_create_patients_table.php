<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->json('name')->notNullable();
            $table->date('birthdate')->index();
            $table->json('telecom')->nullable();
            $table->json('address')->nullable();
            $table->string('sex')->index();
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

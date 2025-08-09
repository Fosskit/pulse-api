<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('practitioners', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->string('code', 64)->unique();
            $table->boolean('active')->default(true);
            $table->json('name')->notNullable();
            $table->json('telecom')->nullable();
            $table->json('address')->nullable();
            $table->enum('sex', ['Female', 'Male'])->nullable();
            $table->date('birthdate')->nullable();
            $table->json('qualification')->nullable();
            $table->json('communication')->nullable();
            $table->json('meta')->nullable();
            $table->commonFields();

            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('practitioners');
    }
};

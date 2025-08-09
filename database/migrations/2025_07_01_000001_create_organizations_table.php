<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->boolean('active')->default(true);
            $table->string('name')->notNullable();
            $table->json('alias')->nullable();
            $table->json('telecom')->nullable();
            $table->json('address')->nullable();
            $table->unsignedBigInteger('part_of')->nullable();
            $table->json('contact')->nullable();
            $table->json('meta')->nullable();
            $table->commonFields();

            $table->foreign('part_of')->references('id')->on('organizations');
            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizations');
    }
};

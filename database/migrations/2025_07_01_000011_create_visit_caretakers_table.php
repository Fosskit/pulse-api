<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('visit_caretakers', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->string('name', 100)->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('sex', 7)->index();
            $table->unsignedBigInteger('relationship_id')->index();
            $table->commonFields();

            $table->foreign('visit_id')->references('id')->on('visits');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_caretakers');
    }
};

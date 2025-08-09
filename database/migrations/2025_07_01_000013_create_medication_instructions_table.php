<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('medication_instructions', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('method_id')->index();
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->decimal('morning', 8, 2);
            $table->decimal('afternoon', 8, 2);
            $table->decimal('evening', 8, 2);
            $table->decimal('night', 8, 2);
            $table->integer('days');
            $table->decimal('quantity', 8, 2)->default(0.00);
            $table->text('note')->nullable();
            $table->commonFields();

        });
    }

    public function down()
    {
        Schema::dropIfExists('medication_instructions');
    }
};

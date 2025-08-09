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
            $table->ulid()->index();
            $table->string('code')->index();
            $table->foreignId('facility_id')->index()->constrained('facilities');
            $table->commonFields();
        });
    }

    public function down()
    {
        Schema::dropIfExists('patients');
    }
};

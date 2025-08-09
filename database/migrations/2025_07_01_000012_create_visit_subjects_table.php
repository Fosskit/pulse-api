<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('visit_subjects', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('visit_id')->nullable()->unique();
            $table->unsignedBigInteger('patient_demographic_id')->nullable()->index();
            $table->unsignedBigInteger('patient_address_id')->nullable()->index();
            $table->string('identity_code')->nullable()->index();
            $table->string('card_code')->nullable()->index();
            $table->unsignedTinyInteger('card_type_id')->nullable()->index();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->commonFields();



            $table->foreign('visit_id')->references('id')->on('visits');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_subjects');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 77)->nullable()->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_category_id')->index();
            $table->unsignedBigInteger('payment_type_id')->index();
            $table->datetime('date')->nullable();
            $table->decimal('total', 8, 2)->default(0.00);
            $table->decimal('percentage_discount', 8, 2)->default(0.00);
            $table->decimal('amount_discount', 8, 2)->default(0.00);
            $table->decimal('discount', 8, 2)->default(0.00);
            $table->decimal('received', 8, 2)->default(0.00);
            $table->string('remark', 70)->nullable();
            $table->commonFields();

            $table->foreign('visit_id')->references('id')->on('visits');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};

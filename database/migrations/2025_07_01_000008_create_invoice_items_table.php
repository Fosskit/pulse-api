<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->nullable()->index();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->unsignedBigInteger('invoiceable_id')->index();
            $table->string('invoiceable_type')->index()->comment('Service / Medicine');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('paid', 10, 2);
            $table->bigInteger('discount_type_id')->nullable()->index();
            $table->bigInteger('payment_type_id')->nullable()->index();
            $table->decimal('discount', 8, 2)->default(0.00);
            $table->commonFields();

            $table->foreign('invoice_id')->references('id')->on('invoices');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
};

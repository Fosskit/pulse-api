<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->decimal('amount', 10, 2); // Can be negative for refunds
            $table->unsignedBigInteger('payment_method_id')->index();
            $table->datetime('payment_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable()->index();
            $table->unsignedBigInteger('original_payment_id')->nullable()->index(); // For refunds
            $table->commonFields();

            $table->foreign('invoice_id')->references('id')->on('invoices');
            $table->foreign('payment_method_id')->references('id')->on('terms');
            $table->foreign('processed_by')->references('id')->on('users');
            $table->foreign('original_payment_id')->references('id')->on('invoice_payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Services
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable()->index();
            $table->string('category', 50)->index()->comment('consultation, procedure, lab, etc.');
            $table->decimal('default_price', 15, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();
        });

        // Price Lists
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('facility_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false)->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Price List Items
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->index()->constrained()->cascadeOnDelete();
            $table->enum('billable_type', ['service', 'medication']);
            $table->unsignedBigInteger('billable_id');
            $table->decimal('price', 15, 2);
            $table->timestamps();
            
            // Create index for each column instead of compound
            $table->index('billable_type');
            $table->index('billable_id');
        });

        // Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->index()->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique()->index();
            $table->string('status', 50)->index()->comment('draft, issued, paid, cancelled, etc.');
            $table->foreignId('price_list_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->date('issue_date')->index();
            $table->date('due_date')->index();
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->index()->constrained()->cascadeOnDelete();
            $table->string('billable_type', 50)->index()->comment('service, medication, encounter, etc.');
            $table->unsignedBigInteger('billable_id')->index();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
        });

        // Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->index()->constrained()->cascadeOnDelete();
            $table->dateTime('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50)->index()->comment('cash, card, insurance, etc.');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('services');
    }
};

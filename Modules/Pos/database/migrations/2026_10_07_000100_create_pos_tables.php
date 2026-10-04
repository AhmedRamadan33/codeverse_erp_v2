<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_registers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->json('name');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // The drawer: cash counted at shift close belongs to this method's account.
            $table->foreignId('cash_payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->unique();
            $table->foreignId('register_id')->constrained('pos_registers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('open');
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 18, 4)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_cash', 18, 4)->nullable();
            $table->decimal('counted_cash', 18, 4)->nullable();
            $table->decimal('cash_difference', 18, 4)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('valuation_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
            // At most one open shift per register.
            $table->unsignedBigInteger('open_register_id')->nullable()->storedAs("if(status = 'open', register_id, null)")->unique();
            $table->index(['user_id', 'status']);
        });

        Schema::create('pos_receipts', function (Blueprint $table) {
            $table->id();
            // Set last in the posting transaction: the sequence is the last lock taken (core-design.md §3.4).
            $table->string('number', 64)->nullable()->unique();
            $table->foreignId('shift_id')->constrained('pos_shifts')->restrictOnDelete();
            $table->foreignId('register_id')->constrained('pos_registers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16)->default('sale');
            $table->foreignId('original_receipt_id')->nullable()->constrained('pos_receipts')->restrictOnDelete();
            $table->date('date');
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 18, 4)->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('paid_total', 18, 4)->default(0);
            $table->decimal('tendered', 18, 4)->nullable();
            $table->decimal('change', 18, 4)->default(0);
            // Credit receipts (and returns of them) post their own entry at once.
            $table->boolean('is_credit')->default(false);
            $table->date('due_date')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['shift_id', 'kind']);
            $table->index(['partner_id', 'date']);
        });

        Schema::create('pos_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('pos_receipts')->cascadeOnDelete();
            $table->foreignId('original_line_id')->nullable()->constrained('pos_receipt_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 18, 4)->nullable();
            $table->decimal('gross', 18, 4);
            $table->decimal('line_discount', 18, 4)->default(0);
            $table->decimal('document_discount', 18, 4)->default(0);
            $table->decimal('net', 18, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 9, 4)->nullable();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->string('batch_number', 64)->nullable();
            $table->json('serials')->nullable();
            // Stock value that left (sale) or came back (return).
            $table->decimal('cost', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('pos_receipt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('pos_receipts')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            // Always positive; on a return receipt it is a refund.
            $table->decimal('amount', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_receipt_payments');
        Schema::dropIfExists('pos_receipt_lines');
        Schema::dropIfExists('pos_receipts');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_registers');
    }
};

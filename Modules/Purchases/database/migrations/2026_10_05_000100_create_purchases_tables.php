<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $header = function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->nullable()->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 16)->default('draft');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            // Document currency, computed and stored on save (core-design.md §5).
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'status']);
        };

        Schema::create('purchase_invoices', function (Blueprint $table) use ($header) {
            $header($table);
            // The supplier's own invoice number, for matching their statement.
            $table->string('supplier_reference', 64)->nullable();
            $table->date('due_date')->nullable();
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 18, 4)->nullable();
        });

        Schema::create('purchase_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 18, 4)->nullable();
            // Computed amounts (document currency), stored so the printed invoice never changes.
            $table->decimal('gross', 18, 4);
            $table->decimal('line_discount', 18, 4)->default(0);
            $table->decimal('document_discount', 18, 4)->default(0);
            $table->decimal('net', 18, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 9, 4)->nullable();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->string('batch_number', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('serials')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) use ($header) {
            $header($table);
            $table->foreignId('purchase_invoice_id')->constrained()->restrictOnDelete();
        });

        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_line_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            // Share of the invoice line returned (document currency).
            $table->decimal('net', 18, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 9, 4)->nullable();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->string('batch_number', 64)->nullable();
            $table->json('serials')->nullable();
            // Stock value that left, base currency (filled when posted).
            $table->decimal('stock_cost', 18, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_invoice_lines');
        Schema::dropIfExists('purchase_invoices');
    }
};

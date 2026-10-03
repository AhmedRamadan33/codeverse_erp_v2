<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price lists (v1 "sales segments"): a price per product unit, assigned to customers.
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 18, 4);
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id', 'unit_id']);
        });

        // Sales data about a partner lives in Sales; Core's partners table stays module-agnostic.
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->foreignId('partner_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

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

        Schema::create('sales_invoices', function (Blueprint $table) use ($header) {
            $header($table);
            $table->date('due_date')->nullable();
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 18, 4)->nullable();
            // Payment taken when posting: creates a receipt voucher allocated to the invoice.
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('paid_amount', 18, 4)->nullable();
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
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
            // Stock value that left (base currency), for margins and returns at original cost.
            $table->decimal('cost', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('sales_returns', function (Blueprint $table) use ($header) {
            $header($table);
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
        });

        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_line_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('net', 18, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 9, 4)->nullable();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->string('batch_number', 64)->nullable();
            $table->json('serials')->nullable();
            // Original issue cost of the returned quantity (base currency).
            $table->decimal('cost', 18, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};

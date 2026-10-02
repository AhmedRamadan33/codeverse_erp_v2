<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->nullable()->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 16)->default('draft');
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            // Optional: who was paid (for the supplier's tax number on the expense report).
            $table->foreignId('partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            // Voucher currency, computed from the lines when saved.
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->string('reference', 64)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_voucher_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            // Net of tax, voucher currency.
            $table->decimal('amount', 18, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            // Rate kept as posted, so later rate changes never alter the document.
            $table->decimal('tax_rate', 9, 4)->nullable();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_voucher_lines');
        Schema::dropIfExists('expense_vouchers');
    }
};

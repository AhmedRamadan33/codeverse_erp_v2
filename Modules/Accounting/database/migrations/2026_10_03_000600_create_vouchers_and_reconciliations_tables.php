<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One table per document type (CLAUDE.md principle 5); both share the same shape.
        foreach (['receipt_vouchers', 'payment_vouchers'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('number', 64)->nullable()->unique();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->date('date');
                $table->string('status', 16)->default('draft');
                $table->foreignId('partner_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->decimal('exchange_rate', 18, 6)->default(1);
                // In the voucher currency.
                $table->decimal('amount', 18, 4);
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
                $table->index(['partner_id', 'status']);
            });
        }

        // Which debit lines a credit line settles, on the same partner and account (core-design.md §6.7).
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_line_id')->constrained('journal_lines')->restrictOnDelete();
            $table->foreignId('credit_line_id')->constrained('journal_lines')->restrictOnDelete();
            // Base currency.
            $table->decimal('amount', 18, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('debit_line_id');
            $table->index('credit_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('payment_vouchers');
        Schema::dropIfExists('receipt_vouchers');
    }
};

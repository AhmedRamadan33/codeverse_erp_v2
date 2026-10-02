<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            // Assigned when posted.
            $table->string('number', 64)->nullable()->unique();
            $table->date('date');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('journal_type', 16);
            $table->string('description')->nullable();
            // The document that produced the entry; null for manual entries.
            $table->nullableMorphs('source');
            $table->string('status', 16)->default('draft');
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            // Base currency.
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            // Signed amount in the foreign currency; null when the line is in base currency.
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount_currency', 18, 4)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('description')->nullable();
            $table->nullableMorphs('source_line');
            $table->timestamps();
            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['partner_id', 'account_id']);
        });

        // Draft lines may be all zero while being edited; posting checks one side is positive.
        DB::statement('alter table journal_lines add constraint journal_lines_amounts_check check (debit >= 0 and credit >= 0 and not (debit > 0 and credit > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};

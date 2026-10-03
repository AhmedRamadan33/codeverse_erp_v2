<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('code', 16)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 64);
            $table->date('expiry_date')->nullable();
            $table->date('manufactured_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'batch_number']);
            $table->index(['product_id', 'expiry_date']);
        });

        Schema::create('stock_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 128);
            $table->string('status', 16);
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'serial_number']);
        });

        // Source of truth for quantities and values (core-design.md §9.1).
        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            // Base units, signed: + in, − out.
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            // Signed like quantity; the sum per product equals its stock value.
            $table->decimal('total_cost', 18, 4);
            $table->string('type', 24);
            $table->morphs('source');
            $table->nullableMorphs('source_line');
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_moves')->restrictOnDelete();
            // The valuation entry; null while valuation is deferred (POS) or for internal transfers.
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'warehouse_id', 'date']);
        });

        Schema::create('stock_move_serials', function (Blueprint $table) {
            $table->foreignId('stock_move_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serial_id')->constrained('stock_serials')->restrictOnDelete();
            $table->primary(['stock_move_id', 'serial_id']);
        });

        // Caches rebuilt from stock_moves by `inventory:check`; written only by Inventory actions under row locks.
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            $table->unsignedBigInteger('batch_scope')->storedAs('coalesce(`batch_id`, 0)');
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id', 'batch_scope']);
        });

        // Weighted average cost per product, company wide (core-design.md D6).
        Schema::create('product_costs', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('total_value', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_costs');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_move_serials');
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('stock_serials');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('warehouses');
    }
};

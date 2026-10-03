<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $documentColumns = function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->nullable()->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 16)->default('draft');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        };

        Schema::create('stock_adjustments', function (Blueprint $table) use ($documentColumns) {
            $documentColumns($table);
            // adjustment (stock count differences, damage...) or opening (first stock with its cost).
            $table->string('kind', 16)->default('adjustment');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            // Signed: + adds stock, − removes it. In the line's unit, then in base units.
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            // Per line unit, for increases; empty = current average cost.
            $table->decimal('unit_cost', 18, 4)->nullable();
            // Filled when posted: the value that entered or left stock.
            $table->decimal('total_cost', 18, 4)->nullable();
            $table->string('batch_number', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('serials')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) use ($documentColumns) {
            $documentColumns($table);
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('total_cost', 18, 4)->nullable();
            $table->string('batch_number', 64)->nullable();
            $table->json('serials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
    }
};

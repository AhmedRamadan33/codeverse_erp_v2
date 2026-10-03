<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('symbol');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->json('name');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->string('type', 16)->default('stockable');
            $table->string('tracking', 16)->default('none');
            // The smallest unit; stock, costing and accounting use it only (core-design.md §8).
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            // Per base unit; a unit may override the sale price.
            $table->decimal('sale_price', 18, 4)->default(0);
            // Reference only: real cost is the weighted average kept by Inventory.
            $table->decimal('purchase_price', 18, 4)->default(0);
            $table->foreignId('sale_tax_id')->nullable()->constrained('taxes')->restrictOnDelete();
            $table->foreignId('purchase_tax_id')->nullable()->constrained('taxes')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            // Base units in one of this unit: 1 for the base unit, e.g. 12 for a carton of 12.
            $table->decimal('factor', 18, 4);
            // Null = base sale price × factor.
            $table->decimal('sale_price', 18, 4)->nullable();
            $table->boolean('is_default_sale')->default(false);
            $table->boolean('is_default_purchase')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // The unit the barcode stands for (a carton barcode sells a carton).
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('units');
    }
};

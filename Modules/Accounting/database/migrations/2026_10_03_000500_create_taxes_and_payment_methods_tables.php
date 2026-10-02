<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accounts for a tax come from the mappings "tax.output" / "tax.input" scoped to the tax,
        // falling back to the default mapping. Country modules (EgyptTax) add their own codes.
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('code', 32)->unique();
            $table->decimal('rate', 9, 4);
            $table->string('type', 16)->default('percent');
            $table->string('scope', 16)->default('both');
            $table->boolean('included_in_price')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('type', 16);
            // The cash box or bank account money goes to or comes from.
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('taxes');
    }
};

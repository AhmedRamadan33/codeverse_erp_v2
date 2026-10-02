<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('company');
            $table->string('name');
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->string('tax_number', 32)->nullable()->index();
            $table->string('national_id', 32)->nullable();
            $table->string('commercial_register', 64)->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            // Null = no limit. Checked by Sales according to sales.credit_limit_mode.
            $table->decimal('credit_limit', 18, 4)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            // Null = visible in every branch.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_customer', 'is_active']);
            $table->index(['is_supplier', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which account to use for a purpose ("sales.revenue"), optionally per scope
        // (a product category, a partner, a warehouse, a tax...). See core-design.md §6.4.
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->string('scope_type', 64)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            // NULLs are distinct in MySQL unique indexes; '' / 0 stand for the default mapping.
            $table->string('scope_type_key', 64)->storedAs("coalesce(`scope_type`, '')");
            $table->unsignedBigInteger('scope_id_key')->storedAs('coalesce(`scope_id`, 0)');
            $table->unique(['key', 'scope_type_key', 'scope_id_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};

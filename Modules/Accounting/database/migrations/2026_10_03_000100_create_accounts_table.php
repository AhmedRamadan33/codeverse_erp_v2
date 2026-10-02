<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->json('name');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            // Group accounts only total their children; postings go to leaf accounts.
            $table->boolean('is_group')->default(false);
            $table->string('type', 16);
            $table->string('subtype', 32)->nullable();
            // Set to restrict the account to one currency (e.g. a USD bank account).
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('requires_partner')->default(false);
            $table->boolean('is_active')->default(true);
            // Referenced by default mappings: can be renamed, not deleted.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

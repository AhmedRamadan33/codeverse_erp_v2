<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            // Null = used by every branch that has no sequence of its own.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('branch_scope')->storedAs('coalesce(`branch_id`, 0)');
            // Tokens: {branch} {yyyy} {yy} {mm}
            $table->string('prefix', 64)->default('');
            $table->unsignedTinyInteger('padding')->default(5);
            $table->string('reset', 16)->default('yearly');
            $table->timestamps();
            $table->unique(['key', 'branch_scope']);
        });

        // One counter per reset period, so documents dated in a previous year keep that year's numbering.
        Schema::create('sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            // '' for reset=never, 'YYYY' for yearly, 'YYYY-MM' for monthly.
            $table->string('period', 7);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['sequence_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
        Schema::dropIfExists('sequences');
    }
};

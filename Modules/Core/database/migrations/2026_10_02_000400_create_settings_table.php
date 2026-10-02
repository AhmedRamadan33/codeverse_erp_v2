<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64);
            $table->string('key', 128);
            $table->json('value');
            // Null = installation-wide value; otherwise a per-branch override.
            // MySQL allows no CASCADE on a base column of a generated column; branches are deactivated, not deleted.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            // MySQL treats NULLs as distinct in unique indexes; 0 stands for "no branch" here.
            $table->unsignedBigInteger('branch_scope')->storedAs('coalesce(`branch_id`, 0)');
            $table->unique(['module', 'key', 'branch_scope']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

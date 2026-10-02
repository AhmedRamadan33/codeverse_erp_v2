<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installed_modules', function (Blueprint $table) {
            $table->string('name', 64)->primary();
            $table->string('version', 32);
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at');
            $table->timestamp('upgraded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
    }
};

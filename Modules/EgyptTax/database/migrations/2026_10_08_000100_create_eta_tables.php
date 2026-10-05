<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row: the taxpayer and its ETA credentials.
        Schema::create('eta_settings', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 16)->default('preprod');
            $table->string('rin', 32)->nullable();
            $table->string('company_trade_name')->nullable();
            $table->string('activity_code', 8)->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            // Tax lines for items sold without a tax.
            $table->string('exempt_tax_type', 8)->default('T1');
            $table->string('exempt_sub_type', 16)->default('V003');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // A POS register registered with ETA. A device may have its own client credentials.
        Schema::create('eta_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->unique()->constrained('pos_registers')->restrictOnDelete();
            $table->string('serial', 64)->unique();
            $table->string('os_version', 64);
            $table->string('model_framework', 64)->nullable();
            $table->text('pre_shared_key')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('branch_code', 16)->default('0');
            $table->json('address');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('eta_item_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_type', 8);
            $table->string('item_code', 100);
            $table->timestamps();
        });

        Schema::create('eta_unit_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('unit_type', 16);
            $table->timestamps();
        });

        Schema::create('eta_tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tax_type', 8);
            $table->string('sub_type', 16);
            $table->timestamps();
        });

        // An E-Receipt issued for a POS receipt. A reissued receipt gets a new row.
        Schema::create('eta_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('pos_receipts')->restrictOnDelete();
            $table->foreignId('device_id')->constrained('eta_devices')->restrictOnDelete();
            // Position in the device's uuid chain; set when the document is built.
            $table->unsignedInteger('chain_no')->nullable();
            $table->char('uuid', 64)->nullable()->unique();
            $table->char('previous_uuid', 64)->nullable();
            $table->char('reference_uuid', 64)->nullable();
            $table->char('reference_old_uuid', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            // The exact JSON sent: the uuid is a hash of it, so it is never re-encoded.
            $table->longText('payload')->nullable();
            $table->string('status', 16);
            $table->json('errors')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('submission_uuid', 64)->nullable();
            $table->string('long_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('eta_receipts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['device_id', 'chain_no']);
            $table->index(['device_id', 'status']);
            $table->index(['status', 'submission_uuid']);
            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eta_receipts');
        Schema::dropIfExists('eta_tax_codes');
        Schema::dropIfExists('eta_unit_codes');
        Schema::dropIfExists('eta_item_codes');
        Schema::dropIfExists('eta_devices');
        Schema::dropIfExists('eta_settings');
    }
};

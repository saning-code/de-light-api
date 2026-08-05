<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('general'); // drinks, rice, hardware, cosmetics, etc.
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->default('Ghana');
            $table->string('logo')->nullable();
            $table->string('currency', 5)->default('GHS');
            $table->string('currency_symbol', 5)->default('₵');
            $table->string('tin_number')->nullable(); // Tax Identification Number
            $table->boolean('charge_tax')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('receipt_settings')->nullable(); // header, footer, show_logo etc.
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};

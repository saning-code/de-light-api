<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('customer_code')->nullable(); // auto-generated
            $table->string('group')->nullable(); // VIP, Regular, Wholesale
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('credit_balance', 15, 2)->default(0); // amount they owe
            $table->decimal('total_purchases', 15, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('avatar')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'shop_id', 'is_active']);
            $table->index(['tenant_id', 'shop_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the default Laravel users table if it exists and recreate with tenant fields
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('pin', 10)->nullable(); // 4-6 digit PIN
            $table->string('role')->default('cashier'); // owner, manager, cashier, salesperson
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('can_give_discount')->default(false);
            $table->decimal('max_discount_percent', 5, 2)->default(0);
            $table->boolean('can_delete_sale')->default(false);
            $table->boolean('can_view_reports')->default(false);
            $table->boolean('can_manage_products')->default(true);
            $table->boolean('can_manage_users')->default(false);
            $table->boolean('can_view_cost_price')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'role', 'is_active']);
            $table->index(['tenant_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('local_id')->nullable(); // from offline device (for deduplication)
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users'); // cashier
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('sale_number'); // e.g. INV-20240101-0001
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('change_given', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0); // unpaid credit
            $table->string('payment_method')->default('cash'); // cash, momo, card, bank, credit, split
            $table->json('payment_breakdown')->nullable(); // for split payments
            $table->string('status')->default('completed'); // completed, voided, refunded, partial
            $table->string('note')->nullable();
            $table->string('reference')->nullable(); // momo transaction ID, etc.
            $table->boolean('is_credit_sale')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'shop_id', 'created_at']);
            $table->index(['tenant_id', 'shop_id', 'user_id']);
            $table->index(['tenant_id', 'shop_id', 'customer_id']);
            $table->index(['tenant_id', 'shop_id', 'status']);
            $table->index(['tenant_id', 'local_id']); // dedup check
            $table->index('sale_number');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name'); // snapshot at time of sale
            $table->string('product_unit')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('profit', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['sale_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};

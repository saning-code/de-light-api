<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('local_id')->nullable();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('purchase_number');
            $table->string('reference_number')->nullable(); // supplier invoice no.
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('status')->default('received'); // ordered, received, partial, returned
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'shop_id', 'purchase_date']);
            $table->index(['tenant_id', 'shop_id', 'supplier_id']);
            $table->index(['tenant_id', 'shop_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name');
            $table->decimal('quantity', 15, 3);
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2)->default(0); // update product price?
            $table->decimal('subtotal', 15, 2);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->timestamps();

            $table->index(['purchase_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};

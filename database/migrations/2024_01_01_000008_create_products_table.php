<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable(); // EAN-13, QR, etc.
            $table->text('description')->nullable();
            $table->string('unit')->default('piece'); // piece, kg, litre, carton, bag, dozen, etc.
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(5);
            $table->decimal('max_stock_level', 15, 3)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->string('image')->nullable();
            $table->json('images')->nullable(); // multiple images
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('expiry_date')->nullable();
            $table->string('batch_number')->nullable();
            $table->json('attributes')->nullable(); // size, color, weight, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'shop_id', 'is_active']);
            $table->index(['tenant_id', 'shop_id', 'barcode']);
            $table->index(['tenant_id', 'shop_id', 'category_id']);
            $table->index(['tenant_id', 'shop_id', 'quantity']); // for low stock queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

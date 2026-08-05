<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // sale, purchase, adjustment, return, transfer, opening
            $table->decimal('quantity', 15, 3); // positive = in, negative = out
            $table->decimal('balance_before', 15, 3)->default(0);
            $table->decimal('balance_after', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('reference_type')->nullable(); // App\Models\Sale, App\Models\Purchase
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'shop_id', 'product_id', 'created_at']);
            $table->index(['tenant_id', 'shop_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

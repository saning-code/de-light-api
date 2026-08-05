<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->decimal('total_amount', 15, 2); // original credit amount
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2); // remaining owed
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid'); // unpaid, partial, paid, overdue, written_off
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'shop_id', 'customer_id', 'status']);
            $table->index(['tenant_id', 'shop_id', 'due_date']);
        });

        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('local_id')->nullable();
            $table->foreignId('credit_sale_id')->constrained('credit_sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('cash');
            $table->string('reference')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['credit_sale_id']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
        Schema::dropIfExists('credit_sales');
    }
};

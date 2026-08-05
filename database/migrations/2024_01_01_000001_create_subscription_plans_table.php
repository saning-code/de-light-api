<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Basic, Standard, Premium
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->integer('max_shops')->default(1);
            $table->integer('max_users')->default(3);
            $table->integer('max_products')->default(500);
            $table->boolean('has_reports')->default(true);
            $table->boolean('has_barcode')->default(true);
            $table->boolean('has_bluetooth_print')->default(true);
            $table->boolean('has_cloud_backup')->default(false);
            $table->boolean('has_multi_shop')->default(false);
            $table->boolean('has_analytics')->default(false);
            $table->boolean('has_api_access')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('trial_days')->default(14);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};

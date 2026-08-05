<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('business_name');
            $table->string('business_code', 20)->unique(); // e.g. DL-001234
            $table->string('business_type')->nullable(); // drinks, rice, hardware, etc.
            $table->string('owner_name');
            $table->string('owner_email')->unique();
            $table->string('owner_phone', 20);
            $table->string('country')->default('Ghana');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('active'); // active, suspended, cancelled, trial
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('timezone')->default('Africa/Accra');
            $table->string('currency', 5)->default('GHS');
            $table->string('currency_symbol', 5)->default('₵');
            $table->json('settings')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'subscription_plan_id']);
            $table->index('business_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

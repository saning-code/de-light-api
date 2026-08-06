<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('super_admins', 'api_token')) {
            Schema::table('super_admins', function (Blueprint $table) {
                $table->string('api_token', 80)->nullable()->unique()->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('super_admins', 'api_token')) {
            Schema::table('super_admins', function (Blueprint $table) {
                $table->dropColumn('api_token');
            });
        }
    }
};

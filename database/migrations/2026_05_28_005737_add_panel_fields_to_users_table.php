<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('email'); // admin | reseller | client
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            $table->string('avatar')->nullable()->after('two_factor_enabled');
            $table->string('timezone')->default('UTC')->after('avatar');
            $table->string('language')->default('en')->after('timezone');
            $table->unsignedBigInteger('plan_id')->nullable()->after('language');
            $table->timestamp('last_login_at')->nullable()->after('plan_id');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->timestamp('suspended_at')->nullable()->after('last_login_ip');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'is_active', 'two_factor_enabled', 'avatar',
                'timezone', 'language', 'plan_id', 'last_login_at',
                'last_login_ip', 'suspended_at', 'suspension_reason',
            ]);
        });
    }
};

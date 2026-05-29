<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->string('home_directory');
            $table->bigInteger('quota_bytes')->default(0); // 0 = unlimited
            $table->boolean('is_active')->default(true);
            $table->boolean('readonly')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ftp_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persists ban/unban events initiated from LaraPanel
        Schema::create('fail2ban_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jail');                        // sshd, nginx-http-auth, etc.
            $table->string('ip_address', 45);
            $table->enum('action', ['ban', 'unban', 'whitelist'])->default('ban');
            $table->string('reason')->nullable();
            $table->unsignedInteger('ban_count')->default(1);
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('initiated_by')->default('system'); // system | admin
            $table->timestamps();

            $table->index(['jail', 'ip_address']);
            $table->index(['user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fail2ban_events');
    }
};

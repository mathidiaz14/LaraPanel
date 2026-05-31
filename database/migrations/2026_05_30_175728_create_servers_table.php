<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('name');                          // "VPS NYC-01"
            $table->string('hostname');                      // IP or FQDN
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username')->default('root');
            $table->text('notes')->nullable();

            // Authentication (values are encrypted at rest)
            $table->enum('auth_type', ['key', 'password'])->default('key');
            $table->text('ssh_private_key')->nullable();     // encrypted
            $table->text('ssh_password')->nullable();        // encrypted

            // Status & health
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamp('last_ping_at')->nullable();
            $table->unsignedSmallInteger('latency_ms')->nullable();

            // Cached system info (refreshed on ping)
            $table->json('os_info')->nullable();              // {os, kernel, ram, cpu, disk, uptime}

            // Special flags
            $table->boolean('is_local')->default(false);     // marks the localhost node
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'is_local']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};

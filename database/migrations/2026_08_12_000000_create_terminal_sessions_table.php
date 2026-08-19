<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Target
            $table->enum('type', ['local', 'ssh'])->default('local');
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();

            // Access & channel identity (opaque, signed by auth callback)
            $table->string('token', 64)->unique();          // bearer for WS auth
            $table->string('channel', 36)->unique();        // private-terminal.{uuid}

            // Local shell context
            $table->string('cwd')->nullable();

            // Lifecycle
            $table->enum('status', ['pending', 'attached', 'closed'])->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_command_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('command', 2000);
            $table->string('cwd', 1024)->default('/var/www');
            $table->enum('status', ['queued', 'running', 'success', 'failed', 'cancelled'])->default('success');
            $table->longText('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->boolean('background')->default(false);
            $table->boolean('cancel_requested')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_command_history');
    }
};

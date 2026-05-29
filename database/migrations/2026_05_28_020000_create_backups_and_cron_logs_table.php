<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->enum('type', ['full', 'files', 'database'])->default('full');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->string('filename')->nullable();         // relative path from backups root
            $table->bigInteger('size_bytes')->default(0);
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::create('cron_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cron_job_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['success', 'failure']);
            $table->text('output')->nullable();
            $table->integer('exit_code')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['cron_job_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_run_logs');
        Schema::dropIfExists('backups');
    }
};

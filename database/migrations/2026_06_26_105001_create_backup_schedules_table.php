<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            
            $table->enum('type', ['full', 'files', 'database'])->default('full');
            $table->string('disk')->default('local');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->integer('retention_count')->default(7);
            
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('command');
            $table->string('schedule');        // cron expression: "0 * * * *"
            $table->string('user')->default('www-data'); // linux user to run as
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable(); // success | failure
            $table->text('last_run_output')->nullable();
            $table->integer('run_count')->default(0);
            $table->integer('fail_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // null = system
            $table->string('action');          // e.g. 'domain.created', 'shell.command', 'user.login'
            $table->string('subject')->nullable(); // the target of the action
            $table->string('subject_type')->nullable(); // e.g. 'App\Models\Domain'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();  // extra context data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('severity')->default('info'); // info | warning | critical
            $table->timestamps();

            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

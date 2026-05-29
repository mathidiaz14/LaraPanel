<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Basic, Pro, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->default(0); // monthly price
            $table->integer('max_domains')->default(1);
            $table->integer('max_subdomains')->default(5);
            $table->integer('max_email_accounts')->default(5);
            $table->integer('max_databases')->default(2);
            $table->integer('max_ftp_accounts')->default(2);
            $table->bigInteger('disk_quota_bytes')->default(1073741824); // 1 GB
            $table->bigInteger('bandwidth_bytes')->default(10737418240); // 10 GB
            $table->integer('max_cron_jobs')->default(5);
            $table->boolean('ssl_enabled')->default(true);
            $table->boolean('backups_enabled')->default(false);
            $table->boolean('terminal_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable(); // extra feature flags
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

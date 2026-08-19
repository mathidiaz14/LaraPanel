<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antivirus_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('files_scanned')->default(0);
            $table->unsignedInteger('infected_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->enum('status', ['running', 'clean', 'infected', 'error'])->default('running');
            $table->boolean('quarantine_enabled')->default(false);
            $table->text('raw_output')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antivirus_scans');
    }
};

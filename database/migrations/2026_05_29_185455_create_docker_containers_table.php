<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('name')->unique();           // Container name (also used as --name flag)
            $table->string('image');                    // Image:tag e.g. nginx:latest
            $table->string('container_id', 64)->nullable(); // Docker short/full ID, updated on start

            // Association
            $table->string('domain')->nullable();       // Optional domain association
            $table->string('compose_stack')->nullable(); // Stack name if managed via Compose
            $table->string('compose_file')->nullable();  // Path to docker-compose.yml on disk

            // Config
            $table->json('ports')->nullable();          // [["8080", "80/tcp"], ...]
            $table->json('env_vars')->nullable();       // ["KEY=value", ...]
            $table->json('volumes')->nullable();        // ["/host:/container", ...]
            $table->text('notes')->nullable();          // User notes

            // State (cached, Docker is source of truth)
            $table->enum('last_status', ['running', 'exited', 'paused', 'restarting', 'unknown'])
                  ->default('unknown');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_containers');
    }
};

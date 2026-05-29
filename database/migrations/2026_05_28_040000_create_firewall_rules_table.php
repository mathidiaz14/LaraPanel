<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                           // Descripción amigable
            $table->enum('action', ['allow', 'deny', 'reject', 'limit'])->default('allow');
            $table->string('port')->nullable();               // 80, 443, 22, 3306, or range 8000:8100
            $table->enum('protocol', ['tcp', 'udp', 'any'])->default('tcp');
            $table->string('source_ip')->nullable();          // null = any, or CIDR: 192.168.1.0/24
            $table->string('destination_ip')->nullable();     // null = any
            $table->enum('direction', ['in', 'out', 'any'])->default('in');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_preset')->default(false);    // System presets cannot be deleted
            $table->string('ufw_rule_id')->nullable();        // Internal UFW rule number
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};

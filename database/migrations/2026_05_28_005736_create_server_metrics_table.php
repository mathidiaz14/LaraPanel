<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->float('cpu_usage');          // percentage 0-100
            $table->float('ram_usage');          // percentage 0-100
            $table->bigInteger('ram_total');     // bytes
            $table->bigInteger('ram_used');      // bytes
            $table->float('disk_usage');         // percentage 0-100
            $table->bigInteger('disk_total');    // bytes
            $table->bigInteger('disk_used');     // bytes
            $table->float('net_in')->default(0);  // bytes/s inbound
            $table->float('net_out')->default(0); // bytes/s outbound
            $table->float('load_1')->default(0);  // load average 1min
            $table->float('load_5')->default(0);  // load average 5min
            $table->float('load_15')->default(0); // load average 15min
            $table->integer('process_count')->default(0);
            $table->json('services')->nullable(); // status of nginx, mysql, etc.
            $table->timestamp('recorded_at');

            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uptime_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uptime_monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // up, down
            $table->integer('response_time_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uptime_pings');
    }
};

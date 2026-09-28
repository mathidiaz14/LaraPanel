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
        Schema::create('server_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->unsignedSmallInteger('score');          // 0-100 composite score
            $table->string('grade', 1);                      // A..F
            $table->json('components')->nullable();          // breakdown [{key,label,weight,value,points,note}]
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_health_snapshots');
    }
};
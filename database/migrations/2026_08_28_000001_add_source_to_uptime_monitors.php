<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uptime_monitors', function (Blueprint $table) {
            // 'auto' = provisionado automáticamente (dominios/dockers del servidor)
            // 'manual' = creado por el usuario (por defecto)
            $table->string('source')->default('manual')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('uptime_monitors', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

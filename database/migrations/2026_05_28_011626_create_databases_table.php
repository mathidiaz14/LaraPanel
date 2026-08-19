<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('db_name')->unique();        // actual MySQL DB name
            $table->string('display_name');             // human label
            $table->string('db_user')->unique();        // MySQL user
            $table->string('db_password_hint')->nullable();
            $table->string('db_host')->default('localhost');
            $table->integer('db_port')->default(3306);
            $table->string('engine')->default('mysql'); // mysql | mariadb | postgresql
            $table->bigInteger('size_bytes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_instances');
    }
};

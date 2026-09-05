<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_ftp_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(21);
            $table->string('protocol', 8)->default('ftp'); // ftp | ftps
            $table->string('username');
            $table->text('password')->nullable();
            $table->boolean('passive')->default(true);
            $table->string('initial_path', 512)->default('/');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_ftp_connections');
    }
};
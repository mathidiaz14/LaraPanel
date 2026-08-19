<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('username');           // e.g. "info"
            $table->string('email')->unique();    // e.g. "info@example.com"
            $table->string('password_hash');
            $table->bigInteger('quota_bytes')->default(524288000); // 500MB
            $table->bigInteger('used_bytes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('can_send')->default(true);
            $table->boolean('can_receive')->default(true);
            $table->json('forwarders')->nullable();  // array of forward addresses
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['username', 'domain_id']);
            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};

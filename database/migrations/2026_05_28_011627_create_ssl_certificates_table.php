<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('provider');             // letsencrypt | custom | selfsigned
            $table->string('status');               // pending | active | expired | failed
            $table->text('certificate')->nullable();    // PEM cert
            $table->text('private_key')->nullable();    // encrypted PEM key
            $table->text('chain')->nullable();          // CA chain
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('last_renewed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('san_domains')->nullable();  // Subject Alternative Names
            $table->timestamps();

            $table->index(['domain_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};

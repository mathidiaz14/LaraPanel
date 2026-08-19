<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();          // example.com
            $table->string('type')->default('main');   // main | subdomain | addon | parked
            $table->string('parent_domain')->nullable(); // for subdomains
            $table->string('document_root');            // /var/www/example.com/public
            $table->string('php_version')->default('8.3');
            $table->string('webserver')->default('nginx'); // nginx | apache
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('ssl_provider')->nullable(); // letsencrypt | custom
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending'); // pending | active | suspended | error
            $table->json('config')->nullable();          // extra nginx/apache config
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};

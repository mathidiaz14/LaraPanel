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
        Schema::create('domain_performance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();

            // 10.1 Under Attack Mode
            $table->boolean('under_attack_mode')->default(false);
            $table->unsignedSmallInteger('attack_rate')->default(10);    // req/s
            $table->unsignedSmallInteger('attack_burst')->default(20);
            $table->unsignedSmallInteger('attack_conn')->default(10);    // max concurrent connections

            // 10.2 FastCGI Microcaching
            $table->boolean('microcache_enabled')->default(false);
            $table->unsignedSmallInteger('microcache_ttl')->default(60); // seconds
            $table->timestamp('microcache_purged_at')->nullable();

            // 10.3 Geo-WAF
            $table->boolean('geo_waf_enabled')->default(false);
            $table->enum('geo_waf_mode', ['block', 'allow'])->default('block');
            $table->json('geo_waf_countries')->nullable();                // ["RU","CN","KP"]

            // 10.4 Orange Cloud / Reverse Proxy
            $table->boolean('orange_cloud')->default(false);
            $table->string('proxy_target')->nullable();                  // http://127.0.0.1:3000
            $table->boolean('proxy_ssl_verify')->default(false);
            $table->unsignedSmallInteger('proxy_timeout')->default(60);
            $table->boolean('proxy_websocket')->default(true);

            // 10.5 GoAccess Analytics
            $table->timestamp('goaccess_generated_at')->nullable();
            $table->string('goaccess_report_path')->nullable();

            // 10.6 Page Rules & SSL Avanzado
            $table->boolean('hsts_enabled')->default(false);
            $table->unsignedInteger('hsts_max_age')->default(31536000);  // 1 year in seconds
            $table->boolean('hsts_include_subdomains')->default(false);
            $table->boolean('hsts_preload')->default(false);
            $table->json('custom_headers')->nullable();                  // [{name: string, value: string}]
            $table->json('redirects')->nullable();                        // [{from: string, to: string, code: 301|302}]
            $table->boolean('brotli_enabled')->default(false);

            $table->timestamps();
            $table->unique('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_performance_settings');
    }
};

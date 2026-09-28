<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ssl_certificates', function (Blueprint $table) {
            $table->string('challenge_type')->nullable()->after('provider');
        });

        // Backfill: wildcards can only be validated with DNS-01 (PowerDNS).
        // Anything else defaults to webroot (HTTP-01), which is the safe
        // default for domains whose HTTP traffic reaches this server.
        DB::table('ssl_certificates')
            ->where('provider', 'letsencrypt')
            ->whereNull('challenge_type')
            ->orderBy('id')
            ->chunkById(200, function ($certs) {
                foreach ($certs as $cert) {
                    $sans = json_decode($cert->san_domains ?? '[]', true) ?: [];
                    $isWildcard = collect($sans)->contains(fn ($san) => str_starts_with((string) $san, '*.'));
                    DB::table('ssl_certificates')
                        ->where('id', $cert->id)
                        ->update(['challenge_type' => $isWildcard ? 'dns_pdns' : 'webroot']);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ssl_certificates', function (Blueprint $table) {
            $table->dropColumn('challenge_type');
        });
    }
};

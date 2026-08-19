<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend email_accounts with spam + sieve fields
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->float('spam_score_threshold')->default(5.0)->after('forwarders');
            $table->enum('spam_action', ['mark', 'reject', 'discard', 'folder'])->default('mark')->after('spam_score_threshold');
            $table->string('spam_folder')->default('Junk')->after('spam_action');
            $table->text('sieve_script')->nullable()->after('spam_folder');
            $table->string('display_name')->nullable()->after('username');
        });

        // Extend ftp_accounts with advanced fields
        Schema::table('ftp_accounts', function (Blueprint $table) {
            $table->unsignedInteger('max_connections')->default(5)->after('readonly');
            $table->unsignedBigInteger('bandwidth_limit_bytes')->default(0)->after('max_connections'); // 0=unlimited
            $table->json('allowed_ips')->nullable()->after('bandwidth_limit_bytes');   // whitelist
            $table->json('blocked_ips')->nullable()->after('allowed_ips');             // blacklist
            $table->string('notes')->nullable()->after('blocked_ips');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn(['spam_score_threshold', 'spam_action', 'spam_folder', 'sieve_script', 'display_name']);
        });

        Schema::table('ftp_accounts', function (Blueprint $table) {
            $table->dropColumn(['max_connections', 'bandwidth_limit_bytes', 'allowed_ips', 'blocked_ips', 'notes']);
        });
    }
};

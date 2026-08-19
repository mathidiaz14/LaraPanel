<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DNS Zones — one zone per domain
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');               // FQDN: example.com.
            $table->string('pdns_zone_id')->nullable(); // PowerDNS zone id (same as name)
            $table->enum('type', ['NATIVE', 'MASTER', 'SLAVE'])->default('NATIVE');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('serial')->default(1);
            $table->string('primary_ns')->default('ns1.larapanel.local');
            $table->string('secondary_ns')->nullable();
            $table->string('admin_email')->default('hostmaster@larapanel.local');
            $table->unsignedInteger('ttl_default')->default(3600);
            $table->unsignedInteger('refresh')->default(86400);
            $table->unsignedInteger('retry')->default(7200);
            $table->unsignedInteger('expire')->default(604800);
            $table->unsignedInteger('minimum_ttl')->default(300);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index('domain_id');
        });

        // DNS Records — individual records within a zone
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name');               // e.g. @ or sub or mail
            $table->enum('type', [
                'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS',
                'SRV', 'CAA', 'PTR', 'SOA', 'ALIAS',
            ]);
            $table->text('content');              // the record value
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedSmallInteger('priority')->default(0); // MX/SRV priority
            $table->boolean('is_disabled')->default(false);
            $table->string('pdns_record_id')->nullable(); // PowerDNS internal ID
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['dns_zone_id', 'type']);
        });

        // Email Aliases — standalone aliases and catch-alls
        Schema::create('email_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('source');             // info@example.com or @example.com (catchall)
            $table->json('destinations');         // ["real@gmail.com", "other@example.com"]
            $table->boolean('is_catchall')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source', 'domain_id']);
            $table->index(['domain_id', 'is_catchall']);
        });

        // Email Autoresponders — vacation/out-of-office replies
        Schema::create('email_autoresponders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->string('subject')->default('Fuera de la oficina');
            $table->text('body');
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('reply_from')->nullable(); // override From header
            $table->unsignedInteger('repeat_interval_days')->default(1);
            $table->timestamps();
        });

        // DKIM Keys — per domain keypairs
        Schema::create('dkim_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('selector')->default('mail'); // mail._domainkey
            $table->string('private_key_path');           // /etc/rspamd/dkim/domain.com.key
            $table->text('public_key');                   // PEM public key
            $table->text('dns_value');                    // TXT record value ready to paste
            $table->unsignedSmallInteger('key_size')->default(2048);
            $table->string('algorithm')->default('rsa');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'selector']);
        });

        // Spam Rules — per-user whitelist/blacklist
        Schema::create('spam_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'whitelist_ip', 'whitelist_email', 'whitelist_domain',
                'blacklist_ip', 'blacklist_email', 'blacklist_domain',
            ]);
            $table->string('value');                      // IP, email, or domain
            $table->enum('action', ['skip', 'reject', 'score_add'])->default('skip');
            $table->float('score_modifier')->default(0.0);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'value']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_rules');
        Schema::dropIfExists('dkim_keys');
        Schema::dropIfExists('email_autoresponders');
        Schema::dropIfExists('email_aliases');
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
    }
};

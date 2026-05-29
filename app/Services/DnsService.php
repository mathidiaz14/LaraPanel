<?php

namespace App\Services;

use App\Models\DnsZone;
use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DnsService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    protected function apiUrl(): string
    {
        return config('larapanel.powerdns.api_url', 'http://127.0.0.1:8053/api/v1');
    }

    protected function apiKey(): string
    {
        return config('larapanel.powerdns.api_key', 'larapanel_pdns_secret');
    }

    protected function pdnsRequest(string $method, string $path, array $data = [])
    {
        if (!app()->isProduction()) {
            return null; // Simulated in dev
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey(),
                'Content-Type' => 'application/json',
            ])->{$method}($this->apiUrl() . $path, $data);

            if ($response->failed()) {
                throw new \RuntimeException("PowerDNS API error [{$response->status()}]: " . $response->body());
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("DnsService API error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new DNS zone for a domain.
     */
    public function createZone(User $user, Domain $domain): DnsZone
    {
        $name = rtrim($domain->name, '.') . '.';

        if (DnsZone::where('user_id', $user->id)->where('name', $domain->name)->exists()) {
            throw new \RuntimeException("La zona DNS para {$domain->name} ya existe.");
        }

        $zone = DnsZone::create([
            'user_id'   => $user->id,
            'domain_id' => $domain->id,
            'name'      => $domain->name,
            'type'      => 'NATIVE',
            'is_active' => true,
            'serial'    => now()->format('YmdH') . '01',
            'primary_ns'  => config('larapanel.dns.primary_ns', 'ns1.' . $domain->name),
            'secondary_ns'=> config('larapanel.dns.secondary_ns', 'ns2.' . $domain->name),
            'admin_email' => 'hostmaster.' . $domain->name,
            'ttl_default' => 3600,
        ]);

        // Create zone in PowerDNS via API
        if (app()->isProduction()) {
            $this->pdnsRequest('post', '/servers/localhost/zones', [
                'name'        => $name,
                'kind'        => 'Native',
                'nameservers' => [$zone->primary_ns . '.', $zone->secondary_ns . '.'],
                'rrsets'      => [],
            ]);
            $zone->update(['pdns_zone_id' => $name]);
        }

        // Seed default records (NS + SOA implied by PowerDNS, plus web defaults)
        $this->addDefaultRecords($zone, $domain);

        AuditLog::record('dns.zone.created', $domain->name, ['zone_id' => $zone->id]);

        return $zone->fresh(['records']);
    }

    /**
     * Seed sensible default records when a zone is first created.
     */
    protected function addDefaultRecords(DnsZone $zone, Domain $domain): void
    {
        $serverIp = config('larapanel.server.public_ip', '0.0.0.0');

        $defaults = [
            ['name' => '@',   'type' => 'A',   'content' => $serverIp,             'ttl' => 3600, 'priority' => 0],
            ['name' => 'www', 'type' => 'A',   'content' => $serverIp,             'ttl' => 3600, 'priority' => 0],
            ['name' => 'mail','type' => 'A',   'content' => $serverIp,             'ttl' => 3600, 'priority' => 0],
            ['name' => '@',   'type' => 'MX',  'content' => 'mail.' . $domain->name, 'ttl' => 3600, 'priority' => 10],
        ];

        foreach ($defaults as $rec) {
            $this->createRecord($zone, $rec);
        }
    }

    /**
     * Delete a zone from PowerDNS and the local database.
     */
    public function deleteZone(DnsZone $zone): void
    {
        if (app()->isProduction() && $zone->pdns_zone_id) {
            $this->pdnsRequest('delete', '/servers/localhost/zones/' . urlencode($zone->pdns_zone_id));
        }

        AuditLog::record('dns.zone.deleted', $zone->name);
        $zone->delete(); // cascades records
    }

    /**
     * Create a DNS record inside a zone.
     */
    public function createRecord(DnsZone $zone, array $data): DnsRecord
    {
        $this->validateRecordContent($data['type'], $data['content'] ?? '');

        $record = DnsRecord::create([
            'dns_zone_id' => $zone->id,
            'name'        => strtolower(trim($data['name'])),
            'type'        => strtoupper($data['type']),
            'content'     => trim($data['content']),
            'ttl'         => (int)($data['ttl'] ?? 3600),
            'priority'    => (int)($data['priority'] ?? 0),
            'is_disabled' => $data['is_disabled'] ?? false,
            'comment'     => $data['comment'] ?? null,
        ]);

        if (app()->isProduction()) {
            $this->syncRecordToPdns($zone, $record, 'REPLACE');
        }

        AuditLog::record('dns.record.created', $zone->name, ['type' => $record->type, 'name' => $record->name]);

        return $record;
    }

    /**
     * Update an existing record.
     */
    public function updateRecord(DnsRecord $record, array $data): DnsRecord
    {
        $this->validateRecordContent($data['type'] ?? $record->type, $data['content'] ?? $record->content);

        // Delete old record from PowerDNS first
        if (app()->isProduction()) {
            $this->syncRecordToPdns($record->zone, $record, 'DELETE');
        }

        $record->update([
            'name'        => strtolower(trim($data['name'] ?? $record->name)),
            'type'        => strtoupper($data['type'] ?? $record->type),
            'content'     => trim($data['content'] ?? $record->content),
            'ttl'         => (int)($data['ttl'] ?? $record->ttl),
            'priority'    => (int)($data['priority'] ?? $record->priority),
            'is_disabled' => $data['is_disabled'] ?? $record->is_disabled,
            'comment'     => $data['comment'] ?? $record->comment,
        ]);

        if (app()->isProduction()) {
            $this->syncRecordToPdns($record->zone, $record, 'REPLACE');
        }

        AuditLog::record('dns.record.updated', $record->zone->name, ['id' => $record->id]);

        return $record->fresh();
    }

    /**
     * Delete a DNS record.
     */
    public function deleteRecord(DnsRecord $record): void
    {
        if (app()->isProduction()) {
            $this->syncRecordToPdns($record->zone, $record, 'DELETE');
        }

        AuditLog::record('dns.record.deleted', $record->zone->name, ['type' => $record->type]);
        $record->delete();
    }

    /**
     * Sync a single record to PowerDNS via PATCH rrsets.
     */
    protected function syncRecordToPdns(DnsZone $zone, DnsRecord $record, string $changetype): void
    {
        $fqdn = $this->recordFqdn($record, $zone);

        $rrset = [
            'name'        => $fqdn,
            'type'        => $record->type,
            'ttl'         => $record->ttl,
            'changetype'  => $changetype,
            'records'     => $changetype === 'DELETE' ? [] : [[
                'content'  => $this->formatContent($record),
                'disabled' => $record->is_disabled,
            ]],
        ];

        $this->pdnsRequest('patch', '/servers/localhost/zones/' . urlencode($zone->pdns_zone_id), [
            'rrsets' => [$rrset],
        ]);
    }

    protected function recordFqdn(DnsRecord $record, DnsZone $zone): string
    {
        $name = $record->name;
        if ($name === '@' || $name === $zone->name || $name === '') {
            return $zone->fqdn();
        }
        return rtrim($name, '.') . '.' . $zone->fqdn();
    }

    protected function formatContent(DnsRecord $record): string
    {
        if ($record->type === 'MX') {
            return $record->priority . ' ' . rtrim($record->content, '.') . '.';
        }
        if ($record->type === 'TXT') {
            // Ensure quoted
            $c = trim($record->content, '"');
            return '"' . $c . '"';
        }
        if (in_array($record->type, ['CNAME', 'NS', 'ALIAS'])) {
            return rtrim($record->content, '.') . '.';
        }
        return $record->content;
    }

    /**
     * Add email template records to a zone (MX, SPF, DMARC).
     */
    public function applyEmailTemplate(DnsZone $zone, Domain $domain): void
    {
        $serverIp = config('larapanel.server.public_ip', '0.0.0.0');

        $emailRecords = [
            ['name' => 'mail', 'type' => 'A',   'content' => $serverIp,                         'ttl' => 3600, 'priority' => 0],
            ['name' => '@',    'type' => 'MX',  'content' => 'mail.' . $domain->name,            'ttl' => 3600, 'priority' => 10],
            ['name' => '@',    'type' => 'TXT', 'content' => 'v=spf1 mx a ip4:' . $serverIp . ' ~all', 'ttl' => 3600, 'priority' => 0],
            ['name' => '_dmarc', 'type' => 'TXT', 'content' => 'v=DMARC1; p=none; rua=mailto:dmarc@' . $domain->name . '; ruf=mailto:dmarc@' . $domain->name . '; adkim=r; aspf=r', 'ttl' => 3600, 'priority' => 0],
        ];

        foreach ($emailRecords as $rec) {
            // Skip if identical record already exists
            $exists = DnsRecord::where('dns_zone_id', $zone->id)
                ->where('name', $rec['name'])
                ->where('type', $rec['type'])
                ->exists();
            if (!$exists) {
                $this->createRecord($zone, $rec);
            }
        }

        AuditLog::record('dns.email.template.applied', $zone->name);
    }

    /**
     * Validate record content by type.
     */
    public function validateRecordContent(string $type, string $content): void
    {
        $content = trim($content);

        match(strtoupper($type)) {
            'A'    => $this->assert(filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false, "El contenido del registro A debe ser una IPv4 válida. Recibido: {$content}"),
            'AAAA' => $this->assert(filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false, "El contenido del registro AAAA debe ser una IPv6 válida."),
            'MX'   => $this->assert(!empty($content), "El registro MX requiere un hostname de destino."),
            'CNAME'=> $this->assert(!empty($content) && !str_contains($content, ' '), "El registro CNAME debe ser un hostname sin espacios."),
            'TXT'  => $this->assert(strlen($content) <= 4096, "El contenido TXT no puede superar 4096 caracteres."),
            default=> null,
        };
    }

    protected function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * Get dev-mode simulated zones for UI testing.
     */
    public function getSimulatedZones(User $user): array
    {
        return DnsZone::with(['records', 'domain'])
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}

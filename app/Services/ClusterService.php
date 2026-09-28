<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\User;

/**
 * ClusterService — load-balancing / staging groups for domains.
 *
 * A domain can serve traffic through an Nginx `upstream` block pointing at a
 * set of backend nodes (the "staging upstreams"). Toggling the group on/off
 * switches the vhost between the local PHP-FPM backend and the proxy group.
 *
 * Backend addresses are stored inside the domain's `config` JSON:
 *   config['staging_upstreams'] = [ ['address' => '10.0.0.11:8080'], ... ]
 *   config['on_staging']        = true|false
 */
class ClusterService
{
    public function members(Domain $domain): array
    {
        return array_values(array_map(function (array $member) {
            $address = trim((string) ($member['address'] ?? ''));

            return [
                'address'  => $address,
                'healthy'  => $this->check($address),
                'weight'   => (int) ($member['weight'] ?? 1),
            ];
        }, $domain->getStagingUpstreams()));
    }

    public function addMember(Domain $domain, string $address, ?User $actor = null): void
    {
        $address = trim($address);
        $this->validateAddress($address);

        $members = $domain->getStagingUpstreams();
        $keys = array_map(fn ($m) => trim((string) ($m['address'] ?? '')), $members);

        if (in_array($address, $keys, true)) {
            throw new \InvalidArgumentException("El nodo {$address} ya está registrado.");
        }

        if (count($members) >= 10) {
            throw new \InvalidArgumentException('Máximo de 10 nodos por grupo de balanceo.');
        }

        $members[] = ['address' => $address, 'weight' => 1];
        $domain->update([
            'config' => array_merge($domain->config ?? [], ['staging_upstreams' => $members]),
        ]);

        AuditLog::record('cluster.member.added', $domain->name, ['address' => $address]);
    }

    public function removeMember(Domain $domain, string $address, ?User $actor = null): void
    {
        $members = array_values(array_filter(
            $domain->getStagingUpstreams(),
            fn ($m) => trim((string) ($m['address'] ?? '')) !== trim($address),
        ));

        $domain->update([
            'config' => array_merge($domain->config ?? [], ['staging_upstreams' => $members]),
        ]);

        // No members left → staging group is meaningless.
        if ($members === [] && $domain->onStaging()) {
            $this->setStaging($domain, false);
        }

        AuditLog::record('cluster.member.removed', $domain->name, ['address' => $address]);
    }

    /**
     * Toggle whether the domain serves traffic through the staging group.
     * Deployment of the Nginx vhost is delegated to DomainService so that
     * reloads etc. stay in one place.
     */
    public function setStaging(Domain $domain, bool $enabled, ?User $actor = null): void
    {
        if ($enabled && $domain->getStagingUpstreams() === []) {
            throw new \InvalidArgumentException('No hay nodos en el grupo de staging. Añade al menos un nodo.');
        }

        $domain->update([
            'config' => array_merge($domain->config ?? [], ['on_staging' => $enabled]),
        ]);

        if (app()->isProduction()) {
            app(DomainService::class)->deployConfigs($domain);
        }

        AuditLog::record(
            $enabled ? 'cluster.staging.enabled' : 'cluster.staging.disabled',
            $domain->name,
            ['nodes' => count($domain->getStagingUpstreams())],
        );
    }

    /**
     * Quick TCP connectivity probe against `host:port`.
     */
    public function check(string $address, int $timeout = 2): bool
    {
        $address = trim($address);

        // Health probes never throw: malformed addresses are simply unhealthy.
        if (!preg_match('/^[a-zA-Z0-9\-\.:]+:\d+$/', $address)) {
            return false;
        }

        $host = $address;
        $port = 80;
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int) $port;
        }

        if ($host === '' || $port < 1 || $port > 65535) {
            return false;
        }

        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($fp) {
            fclose($fp);
            return true;
        }

        return false;
    }

    protected function validateAddress(string $address): void
    {
        $payload = trim((string) $address);
        if ($payload === '') {
            throw new \InvalidArgumentException('La dirección del nodo es obligatoria.');
        }
        if (!preg_match('/^[a-zA-Z0-9\-\.:]+:\d+$/', $payload)) {
            throw new \InvalidArgumentException('Formato inválido. Usa host:puerto (ej: 10.0.0.11:8080).');
        }
    }
}
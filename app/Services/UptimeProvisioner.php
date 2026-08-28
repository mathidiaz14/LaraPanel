<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Server;
use App\Models\UptimeMonitor;
use App\Models\User;
use App\Shell\ServerContext;

/**
 * Auto-provisions uptime monitors for the resources already managed by the
 * panel (hosted domains + Docker containers) so the operator does not have to
 * add them by hand. Manual monitors remain fully supported.
 */
class UptimeProvisioner
{
    public function __construct(
        protected DockerService $docker
    ) {}

    /**
     * Create an HTTP monitor for a hosted domain (idempotent for auto source).
     */
    public function enrollDomain(Domain $domain): ?UptimeMonitor
    {
        if (!$domain->is_active) {
            return null;
        }

        $target = 'https://' . $domain->name;

        if ($this->autoExists('http', $target, $domain->user_id)) {
            return null;
        }

        return UptimeMonitor::create([
            'user_id'         => $domain->user_id,
            'server_id'       => ServerContext::server()?->id,
            'name'            => $domain->name,
            'type'            => 'http',
            'target'          => $target,
            'interval_minutes'=> 5,
            'status'          => 'pending',
            'source'          => 'auto',
        ]);
    }

    /**
     * Create a Docker monitor for a container (idempotent for auto source).
     */
    public function enrollDocker(string $containerName, ?int $userId, ?int $serverId): ?UptimeMonitor
    {
        $name = ltrim($containerName, '/');

        if ($this->autoExists('docker', $name, $userId)) {
            return null;
        }

        return UptimeMonitor::create([
            'user_id'         => $userId,
            'server_id'       => $serverId,
            'name'            => 'Docker: ' . $name,
            'type'            => 'docker',
            'target'          => $name,
            'interval_minutes'=> 5,
            'status'          => 'pending',
            'source'          => 'auto',
        ]);
    }

    /**
     * Enroll every active domain and every live Docker container for the user
     * (or for all users / the server when $user is null). Idempotent.
     */
    public function syncAll(?User $user = null): int
    {
        $count = 0;
        $serverId = ServerContext::server()?->id;

        $domains = Domain::query()
            ->where('is_active', true)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->get();

        foreach ($domains as $domain) {
            if ($this->enrollDomain($domain)) {
                $count++;
            }
        }

        foreach ($this->liveDockerNames() as $containerName) {
            $userId = $user?->id ?? $this->defaultAdminId();
            if ($this->enrollDocker($containerName, $userId, $serverId)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove auto monitors whose underlying resource no longer exists
     * (deleted domain or absent container). Keeps manual monitors untouched.
     */
    public function pruneStale(?User $user = null): int
    {
        $removed = 0;

        $auto = UptimeMonitor::where('source', 'auto')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $liveDocker = collect($this->liveDockerNames())->map(fn ($n) => ltrim($n, '/'));
        $activeDomainTargets = Domain::where('is_active', true)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->pluck('name')
            ->map(fn ($n) => 'https://' . $n)
            ->all();

        foreach ($auto as $monitor) {
            $stillExists = match ($monitor->type) {
                'http'  => in_array($monitor->target, $activeDomainTargets, true),
                'docker' => $liveDocker->contains(ltrim($monitor->target, '/')),
                default => false,
            };

            if (!$stillExists) {
                $monitor->pings()->delete();
                $monitor->delete();
                $removed++;
            }
        }

        return $removed;
    }

    protected function autoExists(string $type, string $target, ?int $userId): bool
    {
        return UptimeMonitor::where('source', 'auto')
            ->where('type', $type)
            ->where('target', $target)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }

    /**
     * @return array<int,string>
     */
    protected function liveDockerNames(): array
    {
        try {
            return array_column($this->docker->listContainers(), 'name');
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function defaultAdminId(): ?int
    {
        return User::where('role', 'admin')->orderBy('id')->value('id');
    }
}

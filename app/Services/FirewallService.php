<?php

namespace App\Services;

use App\Models\FirewallRule;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FirewallService
{
    // Common presets with safe defaults
    public const PRESETS = [
        'ssh'        => ['name' => 'SSH',         'port' => '22',   'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'Acceso remoto SSH'],
        'http'       => ['name' => 'HTTP',         'port' => '80',   'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'Tráfico web HTTP'],
        'https'      => ['name' => 'HTTPS',        'port' => '443',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'Tráfico web HTTPS'],
        'ftp'        => ['name' => 'FTP',          'port' => '21',   'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'FTP pasivo'],
        'ftp_data'   => ['name' => 'FTP Datos',    'port' => '20',   'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'FTP datos activo'],
        'smtp'       => ['name' => 'SMTP',         'port' => '25',   'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'Email entrante SMTP'],
        'smtps'      => ['name' => 'SMTPS',        'port' => '465',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'SMTP seguro'],
        'submission' => ['name' => 'Submission',   'port' => '587',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'Email cliente SMTP'],
        'imap'       => ['name' => 'IMAP',         'port' => '143',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'IMAP email'],
        'imaps'      => ['name' => 'IMAPS',        'port' => '993',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'IMAP seguro'],
        'pop3'       => ['name' => 'POP3',         'port' => '110',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'POP3 email'],
        'pop3s'      => ['name' => 'POP3S',        'port' => '995',  'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'POP3 seguro'],
        'mysql'      => ['name' => 'MySQL',        'port' => '3306', 'protocol' => 'tcp', 'action' => 'deny',  'direction' => 'in',  'notes' => 'MySQL (bloqueado por defecto)'],
        'redis'      => ['name' => 'Redis',        'port' => '6379', 'protocol' => 'tcp', 'action' => 'deny',  'direction' => 'in',  'notes' => 'Redis (bloqueado por defecto)'],
        'dns_udp'    => ['name' => 'DNS UDP',      'port' => '53',   'protocol' => 'udp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'DNS consultas UDP'],
        'dns_tcp'    => ['name' => 'DNS TCP',      'port' => '53',   'protocol' => 'tcp', 'action' => 'allow', 'direction' => 'in',  'notes' => 'DNS transferencia TCP'],
        'rspamd'     => ['name' => 'Rspamd',       'port' => '11334','protocol' => 'tcp', 'action' => 'deny',  'direction' => 'in',  'notes' => 'Rspamd web UI (solo localhost)'],
        'pdns'       => ['name' => 'PowerDNS API', 'port' => '8053', 'protocol' => 'tcp', 'action' => 'deny',  'direction' => 'in',  'notes' => 'PowerDNS API (solo localhost)'],
        'ssh_limit'  => ['name' => 'SSH Brute Force Limit', 'port' => '22', 'protocol' => 'tcp', 'action' => 'limit', 'direction' => 'in', 'notes' => 'Protección contra fuerza bruta SSH'],
    ];

    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Get UFW status and active rules from the system.
     */
    public function getStatus(): array
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedStatus();
        }

        try {
            $result = $this->sudo->run(['ufw', 'status', 'verbose'], checkExit: false);
            $output = $result->stdout;

            return [
                'enabled'    => str_contains($output, 'Status: active'),
                'raw_output' => $output,
                'default_in' => $this->parseDefault($output, 'incoming'),
                'default_out'=> $this->parseDefault($output, 'outgoing'),
            ];
        } catch (\Throwable $e) {
            return ['enabled' => false, 'raw_output' => 'UFW no está instalado o no está disponible.', 'default_in' => 'deny', 'default_out' => 'allow'];
        }
    }

    /**
     * Enable UFW.
     */
    public function enable(): void
    {
        if (app()->isProduction()) {
            $this->sudo->run(['ufw', '--force', 'enable']);
        }
        AuditLog::record('firewall.enabled', 'ufw');
    }

    /**
     * Disable UFW.
     */
    public function disable(): void
    {
        if (app()->isProduction()) {
            $this->sudo->run(['ufw', 'disable']);
        }
        AuditLog::record('firewall.disabled', 'ufw');
    }

    /**
     * Reset UFW to defaults (dangerous — SSH first!).
     */
    public function reset(): void
    {
        if (app()->isProduction()) {
            $this->sudo->run(['ufw', '--force', 'reset']);
        }
        // Re-seed SSH allow
        $user = auth()->user();
        $this->ensureSshAllowed($user);
        AuditLog::record('firewall.reset', 'ufw');
    }

    /**
     * Add a new firewall rule.
     */
    public function addRule(User $user, array $data): FirewallRule
    {
        $rule = FirewallRule::create([
            'user_id'     => $user->id,
            'name'        => $data['name'],
            'action'      => $data['action'],
            'port'        => $data['port'] ?? null,
            'protocol'    => $data['protocol'] ?? 'tcp',
            'source_ip'   => $data['source_ip'] ?? null,
            'destination_ip' => $data['destination_ip'] ?? null,
            'direction'   => $data['direction'] ?? 'in',
            'is_active'   => true,
            'is_preset'   => $data['is_preset'] ?? false,
            'notes'       => $data['notes'] ?? null,
            'sort_order'  => FirewallRule::where('user_id', $user->id)->count(),
        ]);

        if (app()->isProduction()) {
            $this->applyRuleToUfw($rule);
        }

        AuditLog::record('firewall.rule.added', $rule->name, ['port' => $rule->port, 'action' => $rule->action]);

        return $rule;
    }

    /**
     * Delete a firewall rule.
     */
    public function deleteRule(FirewallRule $rule): void
    {
        if ($rule->is_preset) {
            throw new \RuntimeException("Las reglas predeterminadas del sistema no pueden eliminarse.");
        }

        if (app()->isProduction() && $rule->ufw_rule_id) {
            $this->sudo->run(['ufw', 'delete', $rule->ufw_rule_id], checkExit: false);
        } elseif (app()->isProduction()) {
            $args = array_merge(['ufw', 'delete'], $rule->toUfwArgs());
            $this->sudo->run($args, checkExit: false);
        }

        AuditLog::record('firewall.rule.deleted', $rule->name);
        $rule->delete();
    }

    /**
     * Toggle rule active/disabled.
     */
    public function toggleRule(FirewallRule $rule): void
    {
        $rule->update(['is_active' => !$rule->is_active]);

        if (app()->isProduction()) {
            if ($rule->is_active) {
                $this->applyRuleToUfw($rule);
            } else {
                $args = array_merge(['ufw', 'delete'], $rule->toUfwArgs());
                $this->sudo->run($args, checkExit: false);
            }
        }

        AuditLog::record('firewall.rule.toggled', $rule->name, ['active' => $rule->is_active]);
    }

    /**
     * Install default preset rules for a fresh server.
     */
    public function installPresets(User $user): int
    {
        $defaultPresets = ['ssh', 'http', 'https', 'ssh_limit'];
        $installed = 0;

        foreach ($defaultPresets as $key) {
            $preset = self::PRESETS[$key];
            $exists = FirewallRule::where('user_id', $user->id)
                ->where('port', $preset['port'])
                ->where('protocol', $preset['protocol'])
                ->exists();

            if (!$exists) {
                $this->addRule($user, array_merge($preset, ['is_preset' => true]));
                $installed++;
            }
        }

        return $installed;
    }

    /**
     * Apply a single rule to UFW.
     */
    protected function applyRuleToUfw(FirewallRule $rule): void
    {
        $args = array_merge(['ufw'], $rule->toUfwArgs());
        $result = $this->sudo->run($args, checkExit: false);
        Log::info("UFW rule applied: " . implode(' ', $args));

        // Try to capture UFW rule number for future deletion
        $ruleNumber = $this->getLastRuleNumber();
        if ($ruleNumber) {
            $rule->update(['ufw_rule_id' => (string)$ruleNumber]);
        }
    }

    /**
     * Make sure SSH port 22 is allowed before any destructive operations.
     */
    protected function ensureSshAllowed(User $user): void
    {
        $exists = FirewallRule::where('user_id', $user->id)
            ->where('port', '22')
            ->where('action', 'allow')
            ->exists();

        if (!$exists) {
            $this->addRule($user, self::PRESETS['ssh']);
        } elseif (app()->isProduction()) {
            $this->sudo->run(['ufw', 'allow', '22/tcp'], checkExit: false);
        }
    }

    /**
     * Get the last inserted UFW rule number.
     */
    protected function getLastRuleNumber(): ?int
    {
        try {
            $result = $this->sudo->run(['ufw', 'status', 'numbered'], checkExit: false);
            $lines  = array_filter(explode("\n", $result->stdout));
            $last   = end($lines);
            preg_match('/^\[\s*(\d+)\]/', $last, $matches);
            return isset($matches[1]) ? (int)$matches[1] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseDefault(string $output, string $direction): string
    {
        preg_match("/{$direction}:\s+(\w+)/i", $output, $matches);
        return $matches[1] ?? 'deny';
    }

    /**
     * Simulated UFW status for dev mode.
     */
    protected function getSimulatedStatus(): array
    {
        return [
            'enabled'     => true,
            'default_in'  => 'deny',
            'default_out' => 'allow',
            'raw_output'  => implode("\n", [
                'Status: active',
                'Logging: on (low)',
                'Default: deny (incoming), allow (outgoing), deny (routed)',
                '',
                'To                         Action      From',
                '--                         ------      ----',
                '22/tcp                     ALLOW IN    Anywhere',
                '80/tcp                     ALLOW IN    Anywhere',
                '443/tcp                    ALLOW IN    Anywhere',
                '25/tcp                     ALLOW IN    Anywhere',
                '22/tcp (v6)                ALLOW IN    Anywhere (v6)',
                '80/tcp (v6)                ALLOW IN    Anywhere (v6)',
                '443/tcp (v6)               ALLOW IN    Anywhere (v6)',
            ]),
        ];
    }

    /**
     * Get number of active rules.
     */
    public function getActiveRulesCount(User $user): int
    {
        return FirewallRule::where('user_id', $user->id)->where('is_active', true)->count();
    }

    /**
     * Get total connection count from netstat (production).
     */
    public function getConnectionStats(): array
    {
        if (!app()->isProduction()) {
            return [
                'established' => rand(10, 80),
                'time_wait'   => rand(5, 30),
                'listen'      => rand(8, 20),
                'total'       => rand(50, 150),
            ];
        }

        try {
            $result = $this->sudo->run(['ss', '-s'], checkExit: false);
            $output = $result->stdout;
            return [
                'established' => $this->parseNetstatCount($output, 'estab'),
                'time_wait'   => $this->parseNetstatCount($output, 'timewait'),
                'listen'      => $this->parseNetstatCount($output, 'listen'),
                'total'       => $this->parseNetstatCount($output, 'total'),
            ];
        } catch (\Throwable) {
            return ['established' => 0, 'time_wait' => 0, 'listen' => 0, 'total' => 0];
        }
    }

    protected function parseNetstatCount(string $output, string $keyword): int
    {
        preg_match("/{$keyword}[:\s]+(\d+)/i", $output, $m);
        return isset($m[1]) ? (int)$m[1] : 0;
    }
}

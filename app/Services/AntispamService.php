<?php

namespace App\Services;

use App\Models\SpamRule;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AntispamService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    protected function apiUrl(): string
    {
        return config('larapanel.rspamd.api_url', 'http://127.0.0.1:11334');
    }

    protected function apiPassword(): string
    {
        return config('larapanel.rspamd.password', '');
    }

    protected function rspamdRequest(string $method, string $path, array $data = [], bool $isJson = true)
    {
        if (!app()->isProduction()) {
            return $this->getSimulatedResponse($path);
        }

        try {
            $request = Http::withHeaders(['Password' => $this->apiPassword()]);

            if ($isJson) {
                $request = $request->asJson();
            }

            $response = $request->{$method}($this->apiUrl() . $path, $data);

            if ($response->failed()) {
                throw new \RuntimeException("Rspamd API error [{$response->status()}]: " . $response->body());
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("AntispamService: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Rspamd global statistics.
     */
    public function getStats(): array
    {
        $data = $this->rspamdRequest('get', '/stat');

        return [
            'scanned'         => $data['scanned'] ?? 0,
            'spam_count'      => $data['spam_count'] ?? 0,
            'ham_count'       => $data['ham_count'] ?? 0,
            'learned'         => $data['learned'] ?? 0,
            'connections'     => $data['connections'] ?? 0,
            'version'         => $data['version'] ?? 'unknown',
            'uptime'          => $data['uptime'] ?? 0,
            'actions'         => $data['actions'] ?? [],
            'spam_percentage' => $data['scanned'] > 0
                ? round(($data['spam_count'] / $data['scanned']) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get message processing history (last 100).
     */
    public function getHistory(): array
    {
        $data = $this->rspamdRequest('get', '/history');
        return $data['rows'] ?? [];
    }

    /**
     * Scan a raw message and return spam assessment.
     */
    public function testMessage(string $rawMessage): array
    {
        if (!app()->isProduction()) {
            return [
                'score'    => 1.5,
                'action'   => 'no action',
                'is_spam'  => false,
                'symbols'  => ['DKIM_VALID' => ['score' => -1.0], 'SPF_PASS' => ['score' => -0.5]],
                'message'  => 'DEV: simulated scan result',
            ];
        }

        $result = Http::withHeaders([
            'Password'     => $this->apiPassword(),
            'Content-Type' => 'message/rfc822',
        ])->withBody($rawMessage, 'message/rfc822')->post($this->apiUrl() . '/checkv2');

        return $result->json() ?? [];
    }

    /**
     * Add a rule to Rspamd config (whitelist/blacklist) and persist in DB.
     */
    public function addRule(User $user, array $data): SpamRule
    {
        $rule = SpamRule::updateOrCreate(
            [
                'user_id' => $user->id,
                'type'    => $data['type'],
                'value'   => $data['value'],
            ],
            [
                'action'         => $data['action'] ?? 'skip',
                'score_modifier' => $data['score_modifier'] ?? 0.0,
                'notes'          => $data['notes'] ?? null,
                'is_active'      => true,
            ]
        );

        // Write config to Rspamd
        $this->writeRspamdConfig($user);

        AuditLog::record('antispam.rule.added', $data['value'], ['type' => $data['type']]);

        return $rule;
    }

    /**
     * Delete a spam rule.
     */
    public function deleteRule(SpamRule $rule): void
    {
        $user = $rule->user;
        AuditLog::record('antispam.rule.deleted', $rule->value);
        $rule->delete();
        $this->writeRspamdConfig($user);
    }

    /**
     * Toggle rule active status.
     */
    public function toggleRule(SpamRule $rule): void
    {
        $rule->update(['is_active' => !$rule->is_active]);
        $this->writeRspamdConfig($rule->user);
    }

    /**
     * Write/regenerate Rspamd config files from DB rules.
     * Creates: /etc/rspamd/local.d/larapanel_whitelist.conf
     *          /etc/rspamd/local.d/larapanel_blacklist.conf
     */
    protected function writeRspamdConfig(User $user): void
    {
        if (!app()->isProduction()) {
            // Store in storage for visibility
            $this->writeDevConfig($user);
            return;
        }

        $rules = SpamRule::where('user_id', $user->id)->where('is_active', true)->get();

        $whitelistIps    = $rules->where('type', 'whitelist_ip')->pluck('value');
        $blacklistIps    = $rules->where('type', 'blacklist_ip')->pluck('value');
        $whitelistEmails = $rules->where('type', 'whitelist_email')->pluck('value');
        $blacklistEmails = $rules->where('type', 'blacklist_email')->pluck('value');

        // Generate multimap config for whitelist
        $whitelistConf  = "# LaraPanel generated — do not edit manually\n";
        $whitelistConf .= "LARAPANEL_WHITELIST_IP {\n  type = \"ip\";\n  prefilter = true;\n  action = \"accept\";\n  ips = [\n";
        foreach ($whitelistIps as $ip) {
            $whitelistConf .= "    \"{$ip}\",\n";
        }
        $whitelistConf .= "  ];\n}\n";

        $blacklistConf  = "# LaraPanel generated — do not edit manually\n";
        $blacklistConf .= "LARAPANEL_BLACKLIST_IP {\n  type = \"ip\";\n  action = \"reject\";\n  ips = [\n";
        foreach ($blacklistIps as $ip) {
            $blacklistConf .= "    \"{$ip}\",\n";
        }
        $blacklistConf .= "  ];\n}\n";

        $tmpWhitelist = tempnam('/tmp', 'lp_spam_wl_');
        $tmpBlacklist = tempnam('/tmp', 'lp_spam_bl_');

        file_put_contents($tmpWhitelist, $whitelistConf);
        file_put_contents($tmpBlacklist, $blacklistConf);

        $this->sudo->run(['cp', $tmpWhitelist, '/etc/rspamd/local.d/larapanel_whitelist.conf']);
        $this->sudo->run(['cp', $tmpBlacklist, '/etc/rspamd/local.d/larapanel_blacklist.conf']);
        $this->sudo->run(['chmod', '644', '/etc/rspamd/local.d/larapanel_whitelist.conf']);
        $this->sudo->run(['chmod', '644', '/etc/rspamd/local.d/larapanel_blacklist.conf']);

        @unlink($tmpWhitelist);
        @unlink($tmpBlacklist);

        // Reload Rspamd to pick up new config
        $this->sudo->run(['systemctl', 'reload', 'rspamd'], checkExit: false);
    }

    protected function writeDevConfig(User $user): void
    {
        $rules = SpamRule::where('user_id', $user->id)->where('is_active', true)->get();

        $configDir = storage_path('app/public/generated_configs');
        @mkdir($configDir, 0755, true);

        $content  = "# DEV: Rspamd Rules for user {$user->id}\n";
        foreach ($rules as $rule) {
            $content .= "# [{$rule->type}] {$rule->value} → {$rule->action}\n";
        }

        file_put_contents($configDir . '/rspamd_rules.conf', $content);
    }

    /**
     * Flush/retrain the Bayes database.
     */
    public function flushBayesDb(): bool
    {
        if (!app()->isProduction()) {
            return true;
        }

        try {
            $this->rspamdRequest('post', '/learnspam', []);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Simulated responses for development mode.
     */
    protected function getSimulatedResponse(string $path): array
    {
        return match(true) {
            str_contains($path, '/stat') => [
                'scanned'     => 14823,
                'spam_count'  => 2341,
                'ham_count'   => 12482,
                'learned'     => 1056,
                'connections' => 3,
                'version'     => '3.8.4',
                'uptime'      => 864000,
                'actions'     => [
                    'no action'   => 11200,
                    'add header'  => 1282,
                    'rewrite subject' => 341,
                    'reject'      => 1000,
                    'soft reject' => 0,
                ],
            ],
            str_contains($path, '/history') => [
                'rows' => [
                    ['subject' => 'Invoice #4421', 'action' => 'add header', 'score' => 4.5, 'from' => 'sender@domain.com', 'time' => time() - 300],
                    ['subject' => 'FREE MONEY!!!', 'action' => 'reject',     'score' => 18.2,'from' => 'spam@evil.org',     'time' => time() - 600],
                    ['subject' => 'Project update', 'action' => 'no action', 'score' => 0.8, 'from' => 'boss@company.com',  'time' => time() - 900],
                ],
            ],
            default => [],
        };
    }
}

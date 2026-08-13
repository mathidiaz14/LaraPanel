<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Notifications\DomainDownAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckDomainsUptimeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:check-uptime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check uptime of active domains and alert if they are down';

    /**
     * How long (in minutes) to suppress repeated alerts for the same domain.
     */
    protected int $alertCooldownMinutes = 30;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $domains = Domain::where('status', 'active')->with('user')->get();

        foreach ($domains as $domain) {
            $this->checkDomain($domain);
        }
    }

    private function checkDomain(Domain $domain): void
    {
        // Prefer HTTPS if SSL is enabled on the domain, fall back to HTTP
        $scheme = $domain->ssl_enabled ? 'https' : 'http';
        $url    = "{$scheme}://{$domain->name}";

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()   // avoid cert issues causing false DOWN alerts
                ->get($url);

            $status = $response->status();

            // Fix: wrap compound condition in parentheses to avoid operator precedence bug.
            // 4xx considered UP except 500+ (server errors). 401/403 mean the site is alive.
            if ($response->serverError() || ($response->clientError() && $status !== 401 && $status !== 403)) {
                $this->alertDomain($domain, "HTTP {$status}");
            } else {
                // Domain recovered — clear the cooldown so next outage fires immediately
                Cache::forget($this->cooldownKey($domain));
                $this->info("Domain {$domain->name} is UP (HTTP {$status}).");
            }
        } catch (\Exception $e) {
            $this->alertDomain($domain, 'Connection Error: ' . $e->getMessage());
        }
    }

    private function alertDomain(Domain $domain, string $reason): void
    {
        $cacheKey = $this->cooldownKey($domain);

        // Suppress repeated notifications during cooldown window
        if (Cache::has($cacheKey)) {
            $this->line("Domain {$domain->name} still DOWN (alert suppressed — cooldown active). Reason: {$reason}");
            return;
        }

        // Mark cooldown so we don't spam every 5 minutes
        Cache::put($cacheKey, true, now()->addMinutes($this->alertCooldownMinutes));

        Log::warning("Domain DOWN alert for {$domain->name}: {$reason}");
        $this->error("Domain {$domain->name} is DOWN. Reason: {$reason}");

        if ($domain->user) {
            $domain->user->notify(new DomainDownAlert($domain->name, $reason));
        }
    }

    private function cooldownKey(Domain $domain): string
    {
        return "domain_down_alert_{$domain->id}";
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Notifications\DomainDownAlert;
use Illuminate\Console\Command;
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
     * Execute the console command.
     */
    public function handle()
    {
        $domains = Domain::where('status', 'active')->with('user')->get();

        foreach ($domains as $domain) {
            $url = 'http://' . $domain->name;
            try {
                // Send GET request with 10 seconds timeout
                $response = Http::timeout(10)->get($url);

                if ($response->serverError() || $response->clientError() && $response->status() !== 401 && $response->status() !== 403) {
                    $this->alertDomain($domain, "HTTP Status: " . $response->status());
                } else {
                    $this->info("Domain {$domain->name} is UP.");
                }
            } catch (\Exception $e) {
                // Connection errors, timeouts, etc
                $this->alertDomain($domain, "Connection Error: " . $e->getMessage());
            }
        }
    }

    private function alertDomain(Domain $domain, string $reason)
    {
        Log::warning("Domain DOWN alert for {$domain->name}: {$reason}");
        
        $this->error("Domain {$domain->name} is DOWN. Reason: {$reason}");

        if ($domain->user) {
            $domain->user->notify(new DomainDownAlert($domain->name, $reason));
        }
    }
}

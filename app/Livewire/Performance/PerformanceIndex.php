<?php

namespace App\Livewire\Performance;

use App\Models\Domain;
use App\Models\DomainPerformanceSetting;
use App\Services\DomainService;
use App\Services\GeoWafService;
use App\Services\GoAccessService;
use App\Jobs\GenerateGoAccessReport;
use Livewire\Component;

class PerformanceIndex extends Component
{
    // ── State ────────────────────────────────────────────────────────
    public string $activeTab    = 'attack';
    public ?int   $domainId     = null;
    public string $successMessage = '';
    public string $errorMessage   = '';

    // 10.1 Under Attack
    public bool $underAttack   = false;
    public int  $attackRate    = 10;
    public int  $attackBurst   = 20;
    public int  $attackConn    = 10;

    // 10.2 Microcache
    public bool $microcacheEnabled = false;
    public int  $microcacheTtl     = 60;
    public ?string $microcachePurgedAt = null;

    // 10.3 Geo-WAF
    public bool   $geoWafEnabled   = false;
    public string $geoWafMode      = 'block';
    public array  $geoWafCountries = [];
    public string $geoWafCountryAdd = '';

    // 10.4 Orange Cloud
    public bool   $orangeCloud    = false;
    public string $proxyTarget    = '';
    public bool   $proxySslVerify = false;
    public int    $proxyTimeout   = 60;
    public bool   $proxyWebsocket = true;

    // 10.5 GoAccess
    public bool   $generatingReport    = false;
    public ?string $goAccessReportPath = null;
    public ?string $goAccessGeneratedAt = null;

    // 10.6 Page Rules
    public bool   $hstsEnabled           = false;
    public int    $hstsMaxAge            = 31536000;
    public bool   $hstsIncludeSubdomains = false;
    public bool   $hstsPreload           = false;
    public array  $customHeaders         = [];
    public array  $redirects             = [];
    public string $newHeaderName         = '';
    public string $newHeaderValue        = '';
    public string $newRedirectFrom       = '';
    public string $newRedirectTo         = '';
    public int    $newRedirectCode       = 301;

    // ── Lifecycle ───────────────────────────────────────────────────

    public function mount(): void
    {
        // Pre-select first domain if available
        $first = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
        if ($first) {
            $this->domainId = $first->id;
            $this->loadDomainSettings();
        }
    }

    public function updatedDomainId(): void
    {
        $this->loadDomainSettings();
        $this->resetMessages();
    }

    protected function loadDomainSettings(): void
    {
        if (!$this->domainId) return;

        $domain = $this->getDomain();
        if (!$domain) return;

        $perf = $domain->performanceSetting;
        if (!$perf) return;

        // 10.1
        $this->underAttack  = $perf->under_attack_mode;
        $this->attackRate   = $perf->attack_rate;
        $this->attackBurst  = $perf->attack_burst;
        $this->attackConn   = $perf->attack_conn;
        // 10.2
        $this->microcacheEnabled  = $perf->microcache_enabled;
        $this->microcacheTtl      = $perf->microcache_ttl;
        $this->microcachePurgedAt = $perf->microcache_purged_at?->diffForHumans();
        // 10.3
        $this->geoWafEnabled   = $perf->geo_waf_enabled;
        $this->geoWafMode      = $perf->geo_waf_mode;
        $this->geoWafCountries = $perf->geo_waf_countries ?? [];
        // 10.4
        $this->orangeCloud    = $perf->orange_cloud;
        $this->proxyTarget    = $perf->proxy_target ?? '';
        $this->proxySslVerify = $perf->proxy_ssl_verify;
        $this->proxyTimeout   = $perf->proxy_timeout;
        $this->proxyWebsocket = $perf->proxy_websocket;
        // 10.5
        $this->goAccessReportPath  = $perf->goaccess_report_path;
        $this->goAccessGeneratedAt = $perf->goaccess_generated_at?->diffForHumans();
        // 10.6
        $this->hstsEnabled           = $perf->hsts_enabled;
        $this->hstsMaxAge            = $perf->hsts_max_age;
        $this->hstsIncludeSubdomains = $perf->hsts_include_subdomains;
        $this->hstsPreload           = $perf->hsts_preload;
        $this->customHeaders         = $perf->custom_headers ?? [];
        $this->redirects             = $perf->redirects ?? [];
    }

    // ── Helpers ─────────────────────────────────────────────────────

    protected function getDomain(): ?Domain
    {
        if (!$this->domainId) return null;
        return Domain::where('id', $this->domainId)
            ->where('user_id', auth()->id())
            ->with('performanceSetting')
            ->first();
    }

    protected function resetMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    // ── 10.1 Under Attack Mode ──────────────────────────────────────

    public function saveUnderAttack(DomainService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) { $this->errorMessage = 'Dominio no encontrado.'; return; }

        try {
            $service->toggleUnderAttackMode($domain, $this->underAttack, [
                'attack_rate'  => $this->attackRate,
                'attack_burst' => $this->attackBurst,
                'attack_conn'  => $this->attackConn,
            ]);
            $this->successMessage = $this->underAttack
                ? '🛡️ Under Attack Mode activado. Nginx recargado.'
                : '✅ Under Attack Mode desactivado. Nginx recargado.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    // ── 10.2 Microcaching ───────────────────────────────────────────

    public function saveMicrocache(DomainService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) { $this->errorMessage = 'Dominio no encontrado.'; return; }

        try {
            if ($this->microcacheEnabled) {
                $service->enableMicrocache($domain, $this->microcacheTtl);
                $this->successMessage = "⚡ FastCGI Microcache activado (TTL {$this->microcacheTtl}s).";
            } else {
                $service->disableMicrocache($domain);
                $this->successMessage = '✅ Microcache desactivado. Nginx recargado.';
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    public function purgeMicrocache(DomainService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) return;

        try {
            $service->purgeMicrocache($domain);
            $this->microcachePurgedAt = 'hace un momento';
            $this->successMessage = '🗑️ Caché purgado exitosamente.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error purgando caché: ' . $e->getMessage();
        }
    }

    // ── 10.3 Geo-WAF ────────────────────────────────────────────────

    public function addGeoCountry(): void
    {
        $code = strtoupper(trim($this->geoWafCountryAdd));
        if (strlen($code) === 2 && !in_array($code, $this->geoWafCountries)) {
            $this->geoWafCountries[] = $code;
        }
        $this->geoWafCountryAdd = '';
    }

    public function removeGeoCountry(string $code): void
    {
        $this->geoWafCountries = array_values(
            array_filter($this->geoWafCountries, fn($c) => $c !== $code)
        );
    }

    public function saveGeoWaf(DomainService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) { $this->errorMessage = 'Dominio no encontrado.'; return; }

        try {
            $service->saveGeoWaf($domain, [
                'geo_waf_enabled'   => $this->geoWafEnabled,
                'geo_waf_mode'      => $this->geoWafMode,
                'geo_waf_countries' => $this->geoWafCountries,
            ]);
            $this->successMessage = '🌍 Geo-WAF guardado. Nginx recargado.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    public function updateGeoWafDatabase(GeoWafService $geoWafService): void
    {
        $this->resetMessages();
        try {
            $geoWafService->updateDatabase();
            $this->successMessage = '✅ Base de datos GeoLite2 actualizada exitosamente.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    // ── 10.4 Orange Cloud ───────────────────────────────────────────

    public function saveProxyConfig(DomainService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) { $this->errorMessage = 'Dominio no encontrado.'; return; }

        if ($this->orangeCloud && empty($this->proxyTarget)) {
            $this->errorMessage = 'Debes especificar la URL/IP del backend destino.';
            return;
        }

        try {
            $service->saveProxyConfig($domain, [
                'orange_cloud'     => $this->orangeCloud,
                'proxy_target'     => $this->proxyTarget,
                'proxy_ssl_verify' => $this->proxySslVerify,
                'proxy_timeout'    => $this->proxyTimeout,
                'proxy_websocket'  => $this->proxyWebsocket,
            ]);
            $this->successMessage = $this->orangeCloud
                ? "☁️ Reverse proxy activado → {$this->proxyTarget}"
                : '✅ Proxy desactivado. Vhost restaurado.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    // ── 10.5 GoAccess ───────────────────────────────────────────────

    public function generateGoAccessReport(GoAccessService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) { $this->errorMessage = 'Dominio no encontrado.'; return; }

        if (!$service->isInstalled()) {
            $this->errorMessage = 'GoAccess no está instalado en el servidor. Ejecuta `apt install goaccess`.';
            return;
        }

        try {
            $this->generatingReport = true;
            GenerateGoAccessReport::dispatch($domain->id);
            $this->successMessage = '📊 Generando reporte en segundo plano. Recarga la página en unos segundos.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        } finally {
            $this->generatingReport = false;
        }
    }

    // ── 10.6 Page Rules ─────────────────────────────────────────────

    public function addCustomHeader(): void
    {
        $name = trim($this->newHeaderName);
        $val  = trim($this->newHeaderValue);
        if ($name && $val) {
            $this->customHeaders[] = ['name' => $name, 'value' => $val];
            $this->newHeaderName   = '';
            $this->newHeaderValue  = '';
        }
    }

    public function removeCustomHeader(int $index): void
    {
        unset($this->customHeaders[$index]);
        $this->customHeaders = array_values($this->customHeaders);
    }

    public function addRedirect(): void
    {
        $from = trim($this->newRedirectFrom);
        $to   = trim($this->newRedirectTo);
        $code = in_array($this->newRedirectCode, [301, 302]) ? $this->newRedirectCode : 301;
        if ($from && $to) {
            $this->redirects[]   = ['from' => $from, 'to' => $to, 'code' => $code];
            $this->newRedirectFrom = '';
            $this->newRedirectTo   = '';
            $this->newRedirectCode = 301;
        }
    }

    public function removeRedirect(int $index): void
    {
        unset($this->redirects[$index]);
        $this->redirects = array_values($this->redirects);
    }

    public function savePageRules(DomainService $service): void
    {
        $this->resetMessages();
        $domain = $this->getDomain();
        if (!$domain) { $this->errorMessage = 'Dominio no encontrado.'; return; }

        try {
            $service->savePageRules($domain, [
                'hsts_enabled'           => $this->hstsEnabled,
                'hsts_max_age'           => $this->hstsMaxAge,
                'hsts_include_subdomains'=> $this->hstsIncludeSubdomains,
                'hsts_preload'           => $this->hstsPreload,
                'custom_headers'         => $this->customHeaders,
                'redirects'              => $this->redirects,
            ]);
            $this->successMessage = '📋 Page Rules guardados. Nginx recargado.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    // ── Render ──────────────────────────────────────────────────────

    public function render(GeoWafService $geoWafService)
    {
        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $allCountries       = $geoWafService->getAllCountries();
        $geoModuleInstalled = $geoWafService->isModuleInstalled();
        $geoDbAvailable     = $geoWafService->isDatabaseAvailable();

        return view('livewire.performance.performance-index', [
            'domains'            => $domains,
            'allCountries'       => $allCountries,
            'geoModuleInstalled' => $geoModuleInstalled,
            'geoDbAvailable'     => $geoDbAvailable,
        ])->layout('layouts.app', [
            'title'      => 'Performance & WAF',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Performance & WAF</strong>',
        ]);
    }
}

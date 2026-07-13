<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

/**
 * GoAccessService — Generates Nginx access log analytics reports.
 *
 * Uses the `goaccess` binary to parse Nginx combined-format access logs
 * and produce a standalone HTML report that can be served in an iframe.
 */
class GoAccessService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Check if goaccess is installed on the system.
     */
    public function isInstalled(): bool
    {
        if (!app()->isProduction()) {
            return true; // Simulate availability in dev
        }

        try {
            $result = $this->sudo->run(['which', 'goaccess'], checkExit: false);
            return $result->exitCode === 0 && !empty(trim($result->stdout));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Generate a GoAccess HTML report for a domain's Nginx access log.
     * Returns the absolute path to the generated report.
     */
    public function generateReport(Domain $domain): string
    {
        $logPath    = "/var/log/nginx/{$domain->name}.access.log";
        $outputDir  = config('larapanel.goaccess.reports_path', '/var/larapanel/goaccess');
        $outputPath = "{$outputDir}/{$domain->name}.html";

        if (!app()->isProduction()) {
            // Dev: generate a simulated HTML stub
            $html = $this->generateSimulatedReport($domain);
            @mkdir($outputDir, 0755, true);
            file_put_contents($outputPath, $html);

            $domain->getPerformance()->update([
                'goaccess_generated_at' => now(),
                'goaccess_report_path'  => $outputPath,
            ]);

            return $outputPath;
        }

        // Ensure output directory exists
        $this->sudo->run(['mkdir', '-p', $outputDir]);

        // Run goaccess
        $this->sudo->run([
            'goaccess',
            $logPath,
            '-o', $outputPath,
            '--log-format=COMBINED',
            '--no-global-config',
            '--ignore-crawlers',
        ]);

        $domain->getPerformance()->update([
            'goaccess_generated_at' => now(),
            'goaccess_report_path'  => $outputPath,
        ]);

        AuditLog::record('goaccess.report.generated', $domain->name, ['path' => $outputPath]);

        return $outputPath;
    }

    /**
     * Read the generated HTML report content.
     */
    public function readReport(Domain $domain): ?string
    {
        $perf = $domain->performanceSetting;
        if (!$perf || !$perf->goaccess_report_path) {
            return null;
        }

        $path = $perf->goaccess_report_path;

        if (!file_exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }

    /**
     * Delete a generated report file.
     */
    public function deleteReport(Domain $domain): void
    {
        $perf = $domain->performanceSetting;
        if (!$perf || !$perf->goaccess_report_path) {
            return;
        }

        if (app()->isProduction() && file_exists($perf->goaccess_report_path)) {
            $this->sudo->run(['rm', '-f', $perf->goaccess_report_path], checkExit: false);
        }

        $perf->update(['goaccess_generated_at' => null, 'goaccess_report_path' => null]);
    }

    /**
     * Simulated HTML report for local development.
     */
    protected function generateSimulatedReport(Domain $domain): string
    {
        $name = $domain->name;
        $date = now()->format('d/m/Y H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>GoAccess — {$name}</title>
<style>
  body { font-family: Inter, sans-serif; background: #0a0e1a; color: #f1f5f9; margin: 0; padding: 24px; }
  h1   { font-size: 18px; color: #818cf8; margin-bottom: 4px; }
  p    { color: #94a3b8; font-size: 13px; }
  .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 24px 0; }
  .card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; }
  .val  { font-size: 28px; font-weight: 700; color: #f1f5f9; }
  .lbl  { font-size: 11px; color: #475569; margin-top: 4px; }
  .note { color: #f59e0b; font-size: 12px; }
</style>
</head>
<body>
  <h1>📊 GoAccess Analytics — {$name}</h1>
  <p>Reporte simulado generado el {$date} (entorno de desarrollo)</p>
  <p class="note">⚠️ En producción, este reporte se genera con datos reales del log de Nginx.</p>
  <div class="grid">
    <div class="card"><div class="val">12,483</div><div class="lbl">VISITAS ÚNICAS</div></div>
    <div class="card"><div class="val">48,921</div><div class="lbl">PETICIONES TOTALES</div></div>
    <div class="card"><div class="val">2.3 GB</div><div class="lbl">DATOS TRANSFERIDOS</div></div>
    <div class="card"><div class="val">98.2%</div><div class="lbl">RESPUESTAS 200 OK</div></div>
    <div class="card"><div class="val">423</div><div class="lbl">ERRORES 404</div></div>
    <div class="card"><div class="val">1.4s</div><div class="lbl">TIEMPO RESP. PROM.</div></div>
  </div>
</body>
</html>
HTML;
    }
}

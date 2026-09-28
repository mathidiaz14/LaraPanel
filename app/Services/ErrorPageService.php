<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;

/**
 * ErrorPageService — persiste páginas de error personalizadas (401, 403,
 * 404, 500, 502, 503) por dominio y fuerza el re-deploy de la configuración
 * de Nginx para que el cambio se aplique de inmediato.
 */
class ErrorPageService
{
    /** Códigos de error HTTP permitidos. */
    protected const ALLOWED_CODES = [401, 403, 404, 500, 502, 503];

    /** CSS base para páginas de error inline (self-contained). */
    protected const DEFAULT_CSS = <<<'CSS'
        body { margin:0; padding:0; font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif; background:#0f172a; color:#e2e8f0; display:flex; align-items:center; justify-content:center; min-height:100vh; text-align:center; }
        .lp-error-wrap { padding:32px; }
        .lp-error-code { font-size:88px; font-weight:800; color:#38bdf8; line-height:1; margin-bottom:12px; }
        .lp-error-msg { font-size:20px; font-weight:600; margin-bottom:8px; }
        .lp-error-hint { font-size:14px; color:#94a3b8; }
    CSS;

    /**
     * Guarda las páginas de error del dominio y re-despliega su config.
     *
     * @param  array<int,string>  $errorPages  map code => contenido (HTML inline o ruta/URL)
     */
    public function save(Domain $domain, array $errorPages): void
    {
        $payload = [];

        foreach (self::ALLOWED_CODES as $code) {
            $value = trim((string) ($errorPages[$code] ?? $errorPages[(string) $code] ?? ''));

            if ($value === '') {
                continue;
            }

            $payload[(string) $code] = $this->normalize($value);
        }

        $domain->error_pages = $payload;
        $domain->save();

        $this->redeploy($domain);

        AuditLog::record(
            action:  'domain.error_pages.saved',
            subject: $domain->name,
            meta:    ['codes' => array_keys($payload)],
        );
    }

    /**
     * Elimina todas las páginas de error personalizadas del dominio.
     */
    public function clear(Domain $domain): void
    {
        $domain->error_pages = [];
        $domain->save();

        $this->redeploy($domain);

        AuditLog::record(
            action:  'domain.error_pages.cleared',
            subject: $domain->name,
        );
    }

    /**
     * Las referencias externas (ruta abs. o URL) se guardan tal cual; el
     * contenido inline se envuelve en un bootstrap HTML autónomo.
     */
    protected function normalize(string $value): string
    {
        if ($this->isExternalReference($value)) {
            return $value;
        }

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Error</title>'
            . '<style>' . self::DEFAULT_CSS . '</style></head><body>'
            . '<div class="lp-error-wrap">' . $value . '</div></body></html>';
    }

    protected function isExternalReference(string $value): bool
    {
        $value = strtolower(ltrim($value));

        return str_starts_with($value, '/')
            || str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://');
    }

    protected function redeploy(Domain $domain): void
    {
        try {
            app(\App\Services\DomainService::class)->deployConfigs($domain);
        } catch (\Throwable $e) {
            Log::warning('ErrorPageService: re-deploy de config falló', [
                'domain' => $domain->name,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
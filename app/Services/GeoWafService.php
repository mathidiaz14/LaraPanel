<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeoWafService — Country-based traffic blocking via MaxMind GeoLite2 + Nginx GeoIP2 module.
 *
 * Requires:
 *   - libnginx-mod-http-geoip2 installed on the server
 *   - GeoLite2-Country.mmdb at config('larapanel.geowaf.mmdb_path')
 *   - A MaxMind license key in config('larapanel.geowaf.license_key') / MAXMIND_LICENSE_KEY env
 */
class GeoWafService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Check if the Nginx GeoIP2 module is installed.
     */
    public function isModuleInstalled(): bool
    {
        if (!app()->isProduction()) {
            return true; // Simulate in dev
        }

        try {
            $result = $this->sudo->run(
                ['nginx', '-V'],
                checkExit: false
            );
            return str_contains($result->stderr . $result->stdout, 'geoip2');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if the GeoLite2 Country database file exists.
     */
    public function isDatabaseAvailable(): bool
    {
        return file_exists(config('larapanel.geowaf.mmdb_path'));
    }

    /**
     * Download / update the GeoLite2-Country.mmdb database from MaxMind.
     */
    public function updateDatabase(): void
    {
        $licenseKey = config('larapanel.geowaf.license_key');
        $mmdbPath   = config('larapanel.geowaf.mmdb_path');
        $mmdbDir    = dirname($mmdbPath);

        if (!$licenseKey) {
            throw new \RuntimeException(
                'No se configuró la clave de licencia de MaxMind (MAXMIND_LICENSE_KEY). ' .
                'Regístrate gratis en maxmind.com para obtener una.'
            );
        }

        if (!app()->isProduction()) {
            Log::info('[GeoWAF] Dev mode: simulating database update.');
            return;
        }

        $url = "https://download.maxmind.com/app/geoip_download"
             . "?edition_id=GeoLite2-Country"
             . "&license_key={$licenseKey}"
             . "&suffix=tar.gz";

        $tmpArchive = sys_get_temp_dir() . '/GeoLite2-Country.tar.gz';

        $this->sudo->run(['mkdir', '-p', $mmdbDir]);
        $this->sudo->run(['wget', '-q', '-O', $tmpArchive, $url]);
        $this->sudo->run(['tar', '-xzf', $tmpArchive, '-C', '/tmp', '--wildcards', '*.mmdb']);
        $this->sudo->run(['find', '/tmp', '-name', 'GeoLite2-Country.mmdb', '-exec', 'mv', '{}', $mmdbPath, ';']);
        $this->sudo->run(['rm', '-f', $tmpArchive], checkExit: false);
        $this->sudo->run(['chown', 'root:www-data', $mmdbPath]);
        $this->sudo->run(['chmod', '640', $mmdbPath]);

        AuditLog::record('geowaf.database.updated', 'GeoLite2-Country.mmdb');
    }

    /**
     * Get ISO codes of all countries (for UI select).
     * Returns array of ['code' => 'AR', 'name' => 'Argentina'], etc.
     */
    public function getAllCountries(): array
    {
        return [
            ['code' => 'AF', 'name' => 'Afghanistan'],
            ['code' => 'AL', 'name' => 'Albania'],
            ['code' => 'DZ', 'name' => 'Algeria'],
            ['code' => 'AR', 'name' => 'Argentina'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'AT', 'name' => 'Austria'],
            ['code' => 'BY', 'name' => 'Belarus'],
            ['code' => 'BE', 'name' => 'Belgium'],
            ['code' => 'BR', 'name' => 'Brazil'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'CL', 'name' => 'Chile'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'CO', 'name' => 'Colombia'],
            ['code' => 'CU', 'name' => 'Cuba'],
            ['code' => 'CZ', 'name' => 'Czech Republic'],
            ['code' => 'DK', 'name' => 'Denmark'],
            ['code' => 'EG', 'name' => 'Egypt'],
            ['code' => 'FI', 'name' => 'Finland'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'GH', 'name' => 'Ghana'],
            ['code' => 'GR', 'name' => 'Greece'],
            ['code' => 'HK', 'name' => 'Hong Kong'],
            ['code' => 'HU', 'name' => 'Hungary'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'ID', 'name' => 'Indonesia'],
            ['code' => 'IR', 'name' => 'Iran'],
            ['code' => 'IQ', 'name' => 'Iraq'],
            ['code' => 'IE', 'name' => 'Ireland'],
            ['code' => 'IL', 'name' => 'Israel'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'KZ', 'name' => 'Kazakhstan'],
            ['code' => 'KE', 'name' => 'Kenya'],
            ['code' => 'KP', 'name' => 'Korea (North)'],
            ['code' => 'KR', 'name' => 'Korea (South)'],
            ['code' => 'MX', 'name' => 'Mexico'],
            ['code' => 'MA', 'name' => 'Morocco'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'NZ', 'name' => 'New Zealand'],
            ['code' => 'NG', 'name' => 'Nigeria'],
            ['code' => 'NO', 'name' => 'Norway'],
            ['code' => 'PK', 'name' => 'Pakistan'],
            ['code' => 'PE', 'name' => 'Peru'],
            ['code' => 'PH', 'name' => 'Philippines'],
            ['code' => 'PL', 'name' => 'Poland'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'RO', 'name' => 'Romania'],
            ['code' => 'RU', 'name' => 'Russia'],
            ['code' => 'SA', 'name' => 'Saudi Arabia'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'ZA', 'name' => 'South Africa'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'SE', 'name' => 'Sweden'],
            ['code' => 'CH', 'name' => 'Switzerland'],
            ['code' => 'TW', 'name' => 'Taiwan'],
            ['code' => 'TH', 'name' => 'Thailand'],
            ['code' => 'TR', 'name' => 'Turkey'],
            ['code' => 'UA', 'name' => 'Ukraine'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'UY', 'name' => 'Uruguay'],
            ['code' => 'UZ', 'name' => 'Uzbekistan'],
            ['code' => 'VE', 'name' => 'Venezuela'],
            ['code' => 'VN', 'name' => 'Vietnam'],
        ];
    }
}

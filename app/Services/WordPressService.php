<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class WordPressService
{
    /**
     * Checks if a directory contains a valid WordPress installation.
     */
    public function isInstalled(string $path): bool
    {
        if (!app()->isProduction()) {
            return false; // For simulation, assume not installed so we can show the installer
        }

        $result = Process::path($path)->run('wp core is-installed --allow-root');
        return $result->successful();
    }

    /**
     * Installs WordPress using wp-cli.
     */
    public function install(string $path, string $url, string $title, string $adminUser, string $adminPass, string $adminEmail, string $dbName, string $dbUser, string $dbPass): array
    {
        if (!app()->isProduction()) {
            return $this->simulateInstall($path, $url, $title);
        }

        $outputBuffer = [];

        try {
            // 1. Download Core
            $outputBuffer[] = "Descargando WordPress Core...";
            $dl = Process::path($path)->timeout(120)->run('wp core download --allow-root');
            if (!$dl->successful()) throw new \Exception("Error al descargar WordPress: " . $dl->errorOutput());

            // 2. Create wp-config.php (values escaped to prevent shell injection)
            $outputBuffer[] = "Configurando base de datos...";
            $cfgCmd = 'wp config create --dbname=' . escapeshellarg($dbName)
                . ' --dbuser=' . escapeshellarg($dbUser)
                . ' --dbpass=' . escapeshellarg($dbPass)
                . ' --allow-root';
            $cfg = Process::path($path)->run($cfgCmd);
            if (!$cfg->successful()) throw new \Exception("Error configurando wp-config: " . $cfg->errorOutput());

            // 3. Install (values escaped to prevent shell injection)
            $outputBuffer[] = "Instalando base de datos y creando administrador...";
            $installCmd = 'wp core install --url=' . escapeshellarg($url)
                . ' --title=' . escapeshellarg($title)
                . ' --admin_user=' . escapeshellarg($adminUser)
                . ' --admin_password=' . escapeshellarg($adminPass)
                . ' --admin_email=' . escapeshellarg($adminEmail)
                . ' --allow-root';
            $inst = Process::path($path)->timeout(120)->run($installCmd);
            if (!$inst->successful()) throw new \Exception("Error durante la instalación: " . $inst->errorOutput());

            // 4. Update Permalinks
            $outputBuffer[] = "Ajustando enlaces permanentes...";
            Process::path($path)->run("wp rewrite structure '/%postname%/' --allow-root");

            $outputBuffer[] = "✅ Instalación completada exitosamente.";
            return ['success' => true, 'output' => implode("\n", $outputBuffer)];

        } catch (\Throwable $e) {
            $outputBuffer[] = "❌ " . $e->getMessage();
            return ['success' => false, 'output' => implode("\n", $outputBuffer)];
        }
    }

    /**
     * Lists plugins for a given WP install
     */
    public function getPlugins(string $path): array
    {
        if (!app()->isProduction()) return [];

        $result = Process::path($path)->run('wp plugin list --format=json --allow-root');
        if ($result->successful()) {
            return json_decode($result->output(), true) ?? [];
        }
        return [];
    }

    protected function simulateInstall(string $path, string $url, string $title): array
    {
        sleep(2); // Simulate work
        return [
            'success' => true,
            'output'  => "Descargando WordPress Core...\nConfigurando base de datos (Simulado)...\nInstalando base de datos y creando administrador...\nAjustando enlaces permanentes...\n✅ Instalación de '{$title}' en {$url} completada exitosamente (Simulación)."
        ];
    }
}

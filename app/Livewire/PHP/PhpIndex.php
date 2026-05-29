<?php

namespace App\Livewire\PHP;

use App\Services\PhpService;
use App\Models\Domain;
use Livewire\Component;

class PhpIndex extends Component
{
    public ?string $selectedVersion = null; // version selected for editing settings
    public array $settings = [];           // current settings for editing
    public string $successMessage = '';
    public string $errorMessage = '';
    public bool $isSaving = false;

    public function selectVersion(string $version, PhpService $phpService): void
    {
        $this->selectedVersion = $version;
        $this->settings = $phpService->getSettings($version);
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function refreshAll(): void
    {
        // Re-rendering is triggered automatically by Livewire; this method just forces a re-render
        $this->successMessage = 'Estado de los pools FPM actualizado.';
    }

    public function closeSettings(): void
    {
        $this->selectedVersion = null;
        $this->settings = [];
    }

    public function saveSettings(PhpService $phpService): void
    {
        if (!$this->selectedVersion) {
            return;
        }

        $this->isSaving = true;
        
        $success = $phpService->updateSettings($this->selectedVersion, $this->settings);

        if ($success) {
            $this->successMessage = "Configuración para PHP {$this->selectedVersion} actualizada y servicio reiniciado correctamente.";
            $this->closeSettings();
        } else {
            $this->errorMessage = "Error al actualizar la configuración de PHP {$this->selectedVersion}. Verifique los logs.";
        }

        $this->isSaving = false;
    }

    public function restartPhp(string $version, PhpService $phpService): void
    {
        $success = $phpService->restartVersion($version);
        if ($success) {
            $this->successMessage = "Servicio php{$version}-fpm reiniciado correctamente.";
        } else {
            $this->errorMessage = "Error al reiniciar php{$version}-fpm.";
        }
    }

    public function changeDomainPhp(int $domainId, string $newVersion, \App\Services\DomainService $domainService): void
    {
        $domain = Domain::where('id', $domainId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $domainService->changePhpVersion($domain, $newVersion);
            $this->successMessage = "Versión de PHP para {$domain->name} cambiada a {$newVersion} correctamente.";
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al cambiar versión de PHP: " . $e->getMessage();
        }
    }

    public function render(PhpService $phpService)
    {
        // Get all domains grouped by PHP version
        $domainsByVersion = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get()
            ->groupBy('php_version');

        return view('livewire.php.php-index', [
            'phpVersions' => $phpService->getPhpVersionsStatus(),
            'domainsByVersion' => $domainsByVersion,
        ])->layout('layouts.app', [
            'title'      => 'PHP Manager',
            'breadcrumb' => '<span>Sistema</span> / <strong>PHP Manager</strong>',
        ]);
    }
}

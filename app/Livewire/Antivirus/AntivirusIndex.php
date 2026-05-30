<?php

namespace App\Livewire\Antivirus;

use App\Models\AntivirusScan;
use App\Models\QuarantineFile;
use App\Services\AntivirusService;
use Livewire\Component;

class AntivirusIndex extends Component
{
    // ── UI State ──────────────────────────────────────────────────────────────
    public string $activeTab     = 'scanner';
    public bool   $isInstalled   = false;

    // ── Scanner tab ───────────────────────────────────────────────────────────
    public string $scanPath          = '/var/www';
    public bool   $withQuarantine    = true;
    public bool   $isScanning        = false;
    public string $scanOutput        = '';
    public array  $scanSummary       = [];
    public ?array $lastScanResult    = null;
    public array  $scanHistory       = [];

    // ── Quarantine tab ────────────────────────────────────────────────────────
    public array $quarantineFiles = [];

    // ── Definitions tab ───────────────────────────────────────────────────────
    public string $clamVersion  = '';
    public array  $defsInfo     = [];
    public string $daemonStatus = '';
    public string $updateOutput = '';
    public bool   $isUpdating   = false;

    // Path presets
    public array $pathPresets = [
        '/var/www'       => '/var/www (Sitios web)',
        '/home'          => '/home (Usuarios)',
        '/tmp'           => '/tmp (Temporales)',
        '/var/larapanel' => '/var/larapanel (Panel)',
        '/uploads'       => '/uploads',
    ];

    protected array $rules = [
        'scanPath' => 'required|string|min:2|max:255',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    public function mount(AntivirusService $av): void
    {
        $this->isInstalled = $av->isInstalled();

        if ($this->isInstalled) {
            $this->clamVersion  = $av->getVersion();
            $this->defsInfo     = $av->getDefinitionsInfo();
            $this->daemonStatus = $av->getDaemonStatus();
            $this->loadScanHistory($av);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   SCANNER
    // ─────────────────────────────────────────────────────────────────────────

    public function runScan(AntivirusService $av): void
    {
        $this->validate();
        $this->isScanning  = true;
        $this->scanOutput  = '';
        $this->scanSummary = [];
        $this->lastScanResult = null;

        try {
            $scan = $av->scan($this->scanPath, $this->withQuarantine);

            $this->lastScanResult = [
                'id'              => $scan->id,
                'status'          => $scan->status,
                'files_scanned'   => $scan->files_scanned,
                'infected_count'  => $scan->infected_count,
                'error_count'     => $scan->error_count,
                'duration_seconds'=> $scan->duration_seconds,
                'badge_class'     => $scan->statusBadgeClass(),
            ];

            $this->scanOutput = $scan->raw_output ?? '';
            $this->loadScanHistory($av);

            // Refresh quarantine if we sent things there
            if ($this->withQuarantine && $scan->infected_count > 0) {
                $this->loadQuarantine($av);
            }
        } catch (\Throwable $e) {
            $this->scanOutput = "❌ Error: " . $e->getMessage();
        } finally {
            $this->isScanning = false;
        }
    }

    public function loadScanHistory(AntivirusService $av): void
    {
        $this->scanHistory = $av->scanHistory(10)->map(fn($s) => [
            'id'             => $s->id,
            'path'           => $s->path,
            'status'         => $s->status,
            'files_scanned'  => $s->files_scanned,
            'infected_count' => $s->infected_count,
            'duration'       => $s->duration_seconds . 's',
            'badge_class'    => $s->statusBadgeClass(),
            'created_at'     => $s->created_at->format('d/m/Y H:i'),
        ])->all();
    }

    public function viewScanDetail(int $scanId): void
    {
        $scan = AntivirusScan::where('user_id', auth()->id())->find($scanId);
        if ($scan) {
            $this->scanOutput = $scan->raw_output ?? '(sin salida)';
            $this->lastScanResult = [
                'id'               => $scan->id,
                'status'           => $scan->status,
                'files_scanned'    => $scan->files_scanned,
                'infected_count'   => $scan->infected_count,
                'error_count'      => $scan->error_count,
                'duration_seconds' => $scan->duration_seconds,
                'badge_class'      => $scan->statusBadgeClass(),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   QUARANTINE
    // ─────────────────────────────────────────────────────────────────────────

    public function loadQuarantine(AntivirusService $av): void
    {
        $this->quarantineFiles = $av->listQuarantine()->map(fn($f) => [
            'id'             => $f->id,
            'original_path'  => $f->original_path,
            'quarantine_path'=> $f->quarantine_path,
            'threat_name'    => $f->threat_name,
            'file_size'      => $f->formattedSize(),
            'exists'         => $f->existsOnDisk(),
            'created_at'     => $f->created_at->format('d/m/Y H:i'),
        ])->all();

        $this->activeTab = 'quarantine';
    }

    public function deleteQuarantineFile(int $id, AntivirusService $av): void
    {
        try {
            $av->deleteFromQuarantine($id);
            $this->loadQuarantine($av);
        } catch (\Throwable $e) {
            session()->flash('error', "Error al eliminar: " . $e->getMessage());
        }
    }

    public function restoreQuarantineFile(int $id, AntivirusService $av): void
    {
        try {
            $av->restoreFromQuarantine($id);
            $this->loadQuarantine($av);
            session()->flash('success', 'Archivo restaurado correctamente.');
        } catch (\Throwable $e) {
            session()->flash('error', "Error al restaurar: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   DEFINITIONS
    // ─────────────────────────────────────────────────────────────────────────

    public function updateDefinitions(AntivirusService $av): void
    {
        $this->isUpdating   = true;
        $this->updateOutput = '';

        try {
            $this->updateOutput = $av->updateDefinitions();
            $this->clamVersion  = $av->getVersion();
            $this->defsInfo     = $av->getDefinitionsInfo();
        } catch (\Throwable $e) {
            $this->updateOutput = "❌ Error: " . $e->getMessage();
        } finally {
            $this->isUpdating = false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function setTab(string $tab, AntivirusService $av): void
    {
        $this->activeTab = $tab;

        if ($tab === 'quarantine') {
            $this->loadQuarantine($av);
        } elseif ($tab === 'definitions') {
            $this->daemonStatus = $av->getDaemonStatus();
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.antivirus.antivirus-index')->layout('layouts.app', [
            'title'      => 'Antivirus',
            'breadcrumb' => '<span>Seguridad</span> / <strong>Antivirus (ClamAV)</strong>',
        ]);
    }
}

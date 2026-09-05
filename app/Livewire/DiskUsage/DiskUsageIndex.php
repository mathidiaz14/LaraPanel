<?php

namespace App\Livewire\DiskUsage;

use App\Services\DiskUsageService;
use Livewire\Component;

class DiskUsageIndex extends Component
{
    public string $path = '/';

    public array $partitions = [];
    public ?array $scan      = null;

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function mount(DiskUsageService $disk): void
    {
        $this->partitions = $disk->partitions();

        // Fall back to the first existing allowed root
        if (!$disk->isScannable($this->path)) {
            $roots       = $disk->availableRoots();
            $this->path  = $roots[0] ?? '';
        }

        if ($this->path !== '') {
            $this->runScan($disk);
        }
    }

    public function updatedPath(DiskUsageService $disk): void
    {
        $this->clearMessages();
        $this->runScan($disk);
    }

    /**
     * Drill down into a subdirectory shown in the table.
     */
    public function navigate(string $path, DiskUsageService $disk): void
    {
        $this->clearMessages();

        if (!$disk->isScannable($path)) {
            $this->errorMessage = 'No puedes navegar fuera de las zonas permitidas.';
            return;
        }

        $this->path = $path;
        $this->runScan($disk);
    }

    public function goUp(DiskUsageService $disk): void
    {
        $parent = dirname($this->path);
        if ($parent !== $this->path && $disk->isScannable($parent)) {
            $this->path = $parent;
            $this->clearMessages();
            $this->runScan($disk);
        }
    }

    public function refreshScan(DiskUsageService $disk): void
    {
        $this->clearMessages();
        $this->runScan($disk, forceFresh: true);
    }

    protected function runScan(DiskUsageService $disk, bool $forceFresh = false): void
    {
        try {
            if ($forceFresh) {
                \Illuminate\Support\Facades\Cache::forget('disk_usage_scan:' . md5($disk->validatePath($this->path)));
            }
            $this->scan = $disk->scan($this->path);
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
            $this->scan         = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al analizar: ' . $e->getMessage();
            $this->scan         = null;
        }
    }

    private function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render()
    {
        return view('livewire.disk-usage.disk-usage-index')
            ->layout('layouts.app', [
                'title'      => 'Uso de Disco',
                'breadcrumb' => '<span>Sistema</span> / <strong>Uso de Disco</strong>',
            ]);
    }
}

<?php

namespace App\Livewire\Logs;

use App\Services\LogService;
use Livewire\Component;

class LogIndex extends Component
{
    public array $availableLogs = [];
    public ?string $selectedLogPath = null;
    public string $logContent = '';
    public int $linesToFetch = 100;
    public string $searchQuery = '';
    
    // Auto refresh toggle
    public bool $autoRefresh = false;

    public function mount(LogService $logService)
    {
        $this->availableLogs = $logService->getAvailableLogs();
        
        // Select first available log by default
        if (!empty($this->availableLogs)) {
            $first = reset($this->availableLogs);
            $this->selectedLogPath = $first['path'];
            $this->loadLogContent($logService);
        }
    }

    public function selectLog(string $path, LogService $logService)
    {
        $this->selectedLogPath = $path;
        $this->loadLogContent($logService);
    }

    public function loadLogContent(LogService $logService)
    {
        if (!$this->selectedLogPath) return;

        try {
            $content = $logService->tailLog($this->selectedLogPath, $this->linesToFetch);
            
            // Basic filtering if search query is present
            if (!empty($this->searchQuery)) {
                $lines = explode("\n", $content);
                $filtered = array_filter($lines, function($line) {
                    return stripos($line, $this->searchQuery) !== false;
                });
                $content = implode("\n", $filtered);
                if (empty($content)) {
                    $content = "No se encontraron coincidencias para '{$this->searchQuery}'.";
                }
            }
            
            $this->logContent = $content;
        } catch (\Throwable $e) {
            $this->logContent = "Error reading log: " . $e->getMessage();
        }
    }

    public function refreshLog(LogService $logService)
    {
        $this->loadLogContent($logService);
    }

    public function clearLog(LogService $logService)
    {
        if (!$this->selectedLogPath) return;
        
        try {
            $logService->clearLog($this->selectedLogPath);
            $this->loadLogContent($logService);
            session()->flash('message', 'Archivo de log limpiado exitosamente.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Error al limpiar el log: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.logs.log-index')->layout('layouts.app', [
            'title'      => 'Visor de Logs',
            'breadcrumb' => '<span>Sistema</span> / <strong>Logs</strong>',
        ]);
    }
}

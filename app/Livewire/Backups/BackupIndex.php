<?php

namespace App\Livewire\Backups;

use App\Models\Backup;
use App\Models\Domain;
use App\Services\BackupService;
use Livewire\Component;

class BackupIndex extends Component
{
    // Create form
    public string $label = '';
    public string $type = 'full';
    public ?int $domainId = null;
    public string $notes = '';

    // Schedule form
    public string $schedType = 'full';
    public string $schedDisk = 'local';
    public string $schedFrequency = 'weekly';
    public int $schedRetention = 7;
    public ?int $schedDomainId = null;

    // Modals
    public ?int $viewingId = null;

    // Messages
    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'label'    => 'required|string|min:3|max:64',
        'type'     => 'required|in:full,files,database',
        'domainId' => 'nullable|integer|exists:domains,id',
        'notes'    => 'nullable|string|max:255',
    ];

    public function mount(): void
    {
        $this->label = 'Backup ' . now()->format('d/m/Y H:i');
    }

    public function runBackup(BackupService $backupService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $backup = $backupService->create(auth()->user(), [
                'type'      => $this->type,
                'domain_id' => $this->domainId,
                'label'     => $this->label,
                'notes'     => $this->notes,
            ]);

            $this->successMessage = "Backup «{$backup->label}» completado con éxito. Tamaño: {$backup->sizeFormatted()}.";
            $this->label = 'Backup ' . now()->format('d/m/Y H:i');
            $this->notes = '';
            $this->domainId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al crear el backup: ' . $e->getMessage();
        }
    }

    public function deleteBackup(int $id, BackupService $backupService): void
    {
        $backup = Backup::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $backupService->delete($backup);
            $this->successMessage = "Backup eliminado con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function restoreBackup(int $id, BackupService $backupService): void
    {
        $backup = Backup::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        
        try {
            $backupService->restore($backup);
            $this->successMessage = "Backup restaurado con éxito.";
            $this->viewingId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al restaurar: " . $e->getMessage();
        }
    }

    public function downloadBackup(int $id, BackupService $backupService)
    {
        $backup = Backup::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $path   = $backupService->getFullPath($backup);

        if (!$path) {
            $this->errorMessage = "Archivo de backup no encontrado en el servidor.";
            return;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        return response()->download($path, $backup->filename ?? basename($path));
    }

    public function viewBackup(int $id): void
    {
        $this->viewingId = $id;
    }

    public function createSchedule(): void
    {
        $this->validate([
            'schedType' => 'required|in:full,files,database',
            'schedDisk' => 'required|in:local,s3',
            'schedFrequency' => 'required|in:daily,weekly,monthly',
            'schedRetention' => 'required|integer|min:1|max:30',
            'schedDomainId' => 'nullable|integer|exists:domains,id',
        ]);

        try {
            \App\Models\BackupSchedule::create([
                'user_id' => auth()->id(),
                'domain_id' => $this->schedDomainId,
                'type' => $this->schedType,
                'disk' => $this->schedDisk,
                'frequency' => $this->schedFrequency,
                'retention_count' => $this->schedRetention,
                'is_active' => true,
            ]);

            $this->successMessage = "Programación de backup creada con éxito.";
            // Reset fields
            $this->schedDomainId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al crear la programación: " . $e->getMessage();
        }
    }

    public function deleteSchedule(int $id): void
    {
        try {
            \App\Models\BackupSchedule::where('id', $id)->where('user_id', auth()->id())->delete();
            $this->successMessage = "Programación eliminada.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $backups = Backup::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $schedules = \App\Models\BackupSchedule::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $viewing = $this->viewingId
            ? $backups->firstWhere('id', $this->viewingId)
            : null;

        return view('livewire.backups.backup-index', [
            'backups' => $backups,
            'domains' => $domains,
            'schedules' => $schedules,
            'viewing' => $viewing,
        ])->layout('layouts.app', [
            'title'      => 'Backups',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Backups</strong>',
        ]);
    }
}

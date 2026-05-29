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

    public function downloadBackup(int $id, BackupService $backupService)
    {
        $backup = Backup::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $path   = $backupService->getFullPath($backup);

        if (!$path) {
            $this->errorMessage = "Archivo de backup no encontrado en el servidor.";
            return;
        }

        return response()->download($path, $backup->filename);
    }

    public function viewBackup(int $id): void
    {
        $this->viewingId = $id;
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

        $viewing = $this->viewingId
            ? $backups->firstWhere('id', $this->viewingId)
            : null;

        return view('livewire.backups.backup-index', [
            'backups' => $backups,
            'domains' => $domains,
            'viewing' => $viewing,
        ])->layout('layouts.app', [
            'title'      => 'Backups',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Backups</strong>',
        ]);
    }
}

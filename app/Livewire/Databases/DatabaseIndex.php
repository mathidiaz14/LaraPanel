<?php

namespace App\Livewire\Databases;

use App\Models\DatabaseInstance;
use App\Models\Domain;
use App\Services\DatabaseService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

class DatabaseIndex extends Component
{
    use WithFileUploads;
    // Form fields for creating a database
    public string $dbNameSuffix = '';
    public string $dbUserSuffix = '';
    public string $dbPassword = '';
    public ?int $domainId = null;

    // Change password modal fields
    public ?int $changingPasswordId = null;
    public string $newPassword = '';

    // Delete modal fields
    public ?int $deletingId = null;

    // Export / Import
    public ?int $importingId = null;
    public mixed $importFile = null;

    // Alerts
    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'dbNameSuffix' => 'required|string|min:2|max:20|regex:/^[a-zA-Z0-9_]+$/',
        'dbUserSuffix' => 'required|string|min:2|max:20|regex:/^[a-zA-Z0-9_]+$/',
        'dbPassword'   => 'required|string|min:8|max:64',
        'domainId'     => 'nullable|integer|exists:domains,id',
    ];

    public function generateRandomPassword(): void
    {
        $this->dbPassword = Str::random(16);
    }

    public function generateRandomNewPassword(): void
    {
        $this->newPassword = Str::random(16);
    }

    public function createDatabase(DatabaseService $dbService): void
    {
        $this->validate();

        $prefix = auth()->user()->getDbPrefix();
        $fullName = $prefix . $this->dbNameSuffix;
        $fullUser = $prefix . $this->dbUserSuffix;

        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            // Verify if DB name or User already exists
            if (DatabaseInstance::where('db_name', $fullName)->exists()) {
                throw new \RuntimeException("La base de datos {$fullName} ya existe en el panel.");
            }
            if (DatabaseInstance::where('db_user', $fullUser)->exists()) {
                throw new \RuntimeException("El usuario de base de datos {$fullUser} ya existe en el panel.");
            }

            $dbService->create(auth()->user(), [
                'db_name' => $fullName,
                'db_user' => $fullUser,
                'db_password' => $this->dbPassword,
                'domain_id' => $this->domainId,
                'display_name' => $this->dbNameSuffix,
            ]);

            $this->successMessage = "Base de datos {$fullName} y usuario {$fullUser} creados con éxito.";
            
            // Reset form
            $this->dbNameSuffix = '';
            $this->dbUserSuffix = '';
            $this->dbPassword = '';
            $this->domainId = null;

        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirmChangePassword(int $id): void
    {
        $this->changingPasswordId = $id;
        $this->newPassword = '';
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function changePassword(DatabaseService $dbService): void
    {
        $this->validate([
            'newPassword' => 'required|string|min:8|max:64',
        ]);

        $instance = DatabaseInstance::where('id', $this->changingPasswordId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $dbService->changePassword($instance, $this->newPassword);
            $this->successMessage = "Contraseña para el usuario {$instance->db_user} cambiada con éxito.";
            $this->changingPasswordId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function deleteDatabase(DatabaseService $dbService): void
    {
        $instance = DatabaseInstance::where('id', $this->deletingId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $dbService->delete($instance);
            $this->successMessage = "Base de datos {$instance->db_name} eliminada con éxito.";
            $this->deletingId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function refreshSizes(DatabaseService $dbService): void
    {
        $instances = DatabaseInstance::where('user_id', auth()->id())->get();
        foreach ($instances as $inst) {
            $dbService->updateSize($inst);
        }
        $this->successMessage = "Tamaños de las bases de datos actualizados.";
    }

    /**
     * Export (download) a SQL dump of the selected database.
     */
    public function exportDatabase(int $id, DatabaseService $dbService)
    {
        $instance = DatabaseInstance::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $dumpPath = $dbService->exportDump($instance);
            return response()->download($dumpPath, basename($dumpPath), [
                'Content-Type' => 'application/sql',
            ])->deleteFileAfterSend(false);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al exportar: ' . $e->getMessage();
        }
    }

    /**
     * Open import modal for a database.
     */
    public function openImport(int $id): void
    {
        $this->importingId = $id;
        $this->importFile  = null;
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    /**
     * Upload and execute SQL import.
     */
    public function importDatabase(DatabaseService $dbService): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:sql,txt|max:51200', // 50MB max
        ]);

        $instance = DatabaseInstance::where('id', $this->importingId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $sqlPath = $this->importFile->getRealPath();
            $dbService->importDump($instance, $sqlPath);
            $this->successMessage = "Archivo SQL importado con éxito en {$instance->db_name}.";
            $this->importingId = null;
            $this->importFile  = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error al importar: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $databases = DatabaseInstance::with('domain')
            ->where('user_id', auth()->id())
            ->orderBy('db_name')
            ->get();

        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.databases.database-index', [
            'databases' => $databases,
            'domains'   => $domains,
            'dbPrefix'  => auth()->user()->getDbPrefix(),
        ])->layout('layouts.app', [
            'title'      => 'Bases de Datos',
            'breadcrumb' => '<span>Hosting</span> / <strong>Bases de Datos</strong>',
        ]);
    }
}

<?php

namespace App\Livewire\Files;

use App\Services\FileService;
use App\Services\MonitoringService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileManager extends Component
{
    use WithFileUploads;

    public string $currentPath = ''; // Relative to the user's webroot
    
    // File upload
    public $uploads = [];

    // Bulk action state
    public array $selectedItems = [];
    public bool $showBulkMoveModal = false;
    public bool $showBulkCopyModal = false;
    public string $bulkDestDirectory = '';

    // Modals state
    public bool $showCreateFolderModal = false;
    public string $newFolderName = '';

    public bool $showCreateFileModal = false;
    public string $newFileName = '';

    public ?string $renamingPath = null;
    public string $newName = '';

    public ?string $chmodPath = null;
    public string $chmodOctal = '';

    public ?string $editingPath = null;
    public string $editingContent = '';

    // Success/error alerts
    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount(): void
    {
        $this->currentPath = '';
        $this->selectedItems = [];
    }

    public function navigate(string $path): void
    {
        $this->currentPath = $path;
        $this->selectedItems = [];
        $this->resetErrorAlerts();
    }

    public function navigateUp(): void
    {
        if ($this->currentPath === '' || $this->currentPath === '/') {
            return;
        }
        $parts = explode('/', trim($this->currentPath, '/'));
        array_pop($parts);
        $this->currentPath = implode('/', $parts);
        $this->selectedItems = [];
        $this->resetErrorAlerts();
    }

    protected function resetErrorAlerts(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
        $this->uploads = [];
        $this->selectedItems = [];
    }

    /**
     * Create Folder.
     */
    public function createFolder(FileService $fileService): void
    {
        $this->validate([
            'newFolderName' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_\-\.]+$/',
        ]);

        try {
            $fileService->createFolder($this->currentPath, $this->newFolderName);
            $this->successMessage = "Carpeta '{$this->newFolderName}' creada con éxito.";
            $this->showCreateFolderModal = false;
            $this->newFolderName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Create File.
     */
    public function createFile(FileService $fileService): void
    {
        $this->validate([
            'newFileName' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_\-\.]+$/',
        ]);

        try {
            $fileService->createFile($this->currentPath, $this->newFileName);
            $this->successMessage = "Archivo '{$this->newFileName}' creado con éxito.";
            $this->showCreateFileModal = false;
            $this->newFileName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Delete resource.
     */
    public function deleteItem(string $name, FileService $fileService): void
    {
        $path = $this->currentPath . '/' . $name;
        try {
            $fileService->delete($path);
            $this->successMessage = "Recurso eliminado correctamente.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Confirm rename.
     */
    public function openRenameModal(string $name): void
    {
        $this->renamingPath = $this->currentPath . '/' . $name;
        $this->newName = $name;
        $this->resetErrorAlerts();
    }

    public function renameItem(FileService $fileService): void
    {
        $this->validate([
            'newName' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_\-\.]+$/',
        ]);

        try {
            $fileService->rename($this->renamingPath, $this->newName);
            $this->successMessage = "Cambiado de nombre a '{$this->newName}' con éxito.";
            $this->renamingPath = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Chmod Modal.
     */
    public function openChmodModal(string $name, string $currentPerms): void
    {
        $this->chmodPath = $this->currentPath . '/' . $name;
        $this->chmodOctal = $currentPerms;
        $this->resetErrorAlerts();
    }

    public function saveChmod(FileService $fileService): void
    {
        $this->validate([
            'chmodOctal' => 'required|string|regex:/^[0-7]{3,4}$/',
        ]);

        try {
            $fileService->chmod($this->chmodPath, $this->chmodOctal);
            $this->successMessage = "Permisos actualizados con éxito.";
            $this->chmodPath = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Open Monaco Editor.
     */
    public function editFile(string $name, FileService $fileService): void
    {
        $this->editingPath = $this->currentPath . '/' . $name;
        try {
            $this->editingContent = $fileService->getFileContent($this->editingPath);
            $this->resetErrorAlerts();
            $this->dispatch('open-editor', content: $this->editingContent, filename: $name);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->editingPath = null;
        }
    }

    public function saveFileContent(string $content, FileService $fileService): void
    {
        if (!$this->editingPath) {
            return;
        }

        try {
            $fileService->updateFileContent($this->editingPath, $content);
            $this->successMessage = "Archivo guardado correctamente.";
            $this->editingPath = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * File Download.
     */
    public function downloadItem(string $name, FileService $fileService)
    {
        $path = $this->currentPath . '/' . $name;
        try {
            $absPath = $fileService->resolvePath($path);
            if (is_dir($absPath)) {
                throw new \RuntimeException("No se pueden descargar directorios directamente. Comprímalo primero.");
            }
            return response()->download($absPath);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return null;
        }
    }

    /**
     * Compression actions.
     */
    public function zipItem(string $name, FileService $fileService): void
    {
        $path = $this->currentPath . '/' . $name;
        $zipName = $name . '.zip';
        try {
            $fileService->zip($path, $zipName);
            $this->successMessage = "Archivo comprimido como {$zipName} con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public $showUnzipModal = false;
    public $unzipItemName = '';

    public function startUnzip(string $name)
    {
        $this->unzipItemName = $name;
        $this->showUnzipModal = true;
    }

    public function processUnzip(FileService $fileService): void
    {
        $path = $this->currentPath . '/' . $this->unzipItemName;
        try {
            $fileService->unzipStream($path, function ($filename, $current, $total) {
                // Enviar la línea extraída al log
                $this->stream(
                    to: 'unzip-log',
                    content: "<div class='text-xs text-gray-300 truncate'>$filename</div>",
                    replace: false,
                );
                
                // Actualizar la barra de progreso
                $percentage = round(($current / $total) * 100);
                $this->stream(
                    to: 'unzip-progress',
                    content: "<div style='width: {$percentage}%' class='bg-blue-500 h-full rounded-full transition-all duration-300'></div>",
                    replace: true,
                );
                
                // Enviar porcentaje al indicador numérico
                $this->stream(
                    to: 'unzip-percentage',
                    content: $percentage . "%",
                    replace: true,
                );
                
                return true;
            });
            
            $this->successMessage = "Archivo descomprimido con éxito.";
            $this->showUnzipModal = false;
            $this->loadItems($fileService);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->showUnzipModal = false;
        }
    }

    /**
     * File upload handler.
     */
    public function updatedUploads(FileService $fileService): void
    {
        $this->validate([
            'uploads.*' => 'file|max:2048000', // 2GB max file size
        ]);

        try {
            foreach ($this->uploads as $upload) {
                $filename = $upload->getClientOriginalName();
                $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
                // Subir como archivo temporal
                $tmpPath = $upload->storeAs('livewire-tmp', $filename);
                $fullTmpPath = \Illuminate\Support\Facades\Storage::disk('local')->path($tmpPath);
                
                // Mover al destino real usando el resolvePath para evitar errores de permisos si el directorio webroot es de root
                $destPath = $fileService->resolvePath($this->currentPath . '/' . $filename);
                
                if (app()->isProduction()) {
                    // Mover usando sudo cp y chown www-data
                    app(\App\Shell\SudoExecutor::class)->run(['cp', $fullTmpPath, $destPath]);
                    app(\App\Shell\SudoExecutor::class)->run(['chown', 'www-data:www-data', $destPath]);
                    unlink($fullTmpPath);
                } else {
                    rename($fullTmpPath, $destPath);
                }
            }
            $this->successMessage = "Archivos subidos correctamente.";
            $this->uploads = [];
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al subir archivos: " . $e->getMessage();
        }
    }

    /**
     * Delete selected items.
     */
    public function deleteSelected(FileService $fileService): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        try {
            $paths = array_map(fn($item) => $this->currentPath . '/' . $item, $this->selectedItems);
            $fileService->deleteMultiple($paths);
            $this->successMessage = "Elementos seleccionados eliminados correctamente.";
            $this->selectedItems = [];
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Compress selected items.
     */
    public function zipSelected(FileService $fileService): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->validate([
            'newFolderName' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_\-\.]+$/',
        ]);

        $zipName = $this->newFolderName;
        if (!str_ends_with(strtolower($zipName), '.zip')) {
            $zipName .= '.zip';
        }

        try {
            $zipPath = $this->currentPath . '/' . $zipName;
            
            // Si solo es uno, usamos el zip estándar
            if (count($this->selectedItems) === 1) {
                $fileService->zip($this->currentPath . '/' . $this->selectedItems[0], $zipName);
            } else {
                $absZipPath = $fileService->resolvePath($zipPath);
                
                if (class_exists(\ZipArchive::class)) {
                    $zip = new \ZipArchive();
                    if ($zip->open($absZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                        foreach ($this->selectedItems as $item) {
                            $itemPath = $this->currentPath . '/' . $item;
                            $absItemPath = $fileService->resolvePath($itemPath);
                            
                            if (is_dir($absItemPath)) {
                                $files = new \RecursiveIteratorIterator(
                                    new \RecursiveDirectoryIterator($absItemPath),
                                    \RecursiveIteratorIterator::LEAVES_ONLY
                                );
                                foreach ($files as $file) {
                                    if (!$file->isDir()) {
                                        $filePath = $file->getRealPath();
                                        $relativePathInZip = $item . '/' . substr($filePath, strlen($absItemPath) + 1);
                                        $zip->addFile($filePath, $relativePathInZip);
                                    }
                                }
                            } else {
                                $zip->addFile($absItemPath, $item);
                            }
                        }
                        $zip->close();
                    } else {
                        throw new \RuntimeException("No se pudo crear el archivo zip.");
                    }
                } else {
                    throw new \RuntimeException("La clase ZipArchive no está disponible en PHP.");
                }
            }

            $this->successMessage = "Comprimido como {$zipName} con éxito.";
            $this->selectedItems = [];
            $this->showCreateFolderModal = false; // Usamos este modal para el prompt del zip
            $this->newFolderName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Move selected items.
     */
    public function moveSelected(FileService $fileService): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->validate([
            'bulkDestDirectory' => 'required|string|regex:/^[a-zA-Z0-9_\-\.\/]*$/',
        ]);

        try {
            $paths = array_map(fn($item) => $this->currentPath . '/' . $item, $this->selectedItems);
            $fileService->moveMultiple($paths, $this->bulkDestDirectory);
            $this->successMessage = "Elementos movidos con éxito a '{$this->bulkDestDirectory}'.";
            $this->selectedItems = [];
            $this->showBulkMoveModal = false;
            $this->bulkDestDirectory = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Copy selected items.
     */
    public function copySelected(FileService $fileService): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $this->validate([
            'bulkDestDirectory' => 'required|string|regex:/^[a-zA-Z0-9_\-\.\/]*$/',
        ]);

        try {
            $paths = array_map(fn($item) => $this->currentPath . '/' . $item, $this->selectedItems);
            $fileService->copyMultiple($paths, $this->bulkDestDirectory);
            $this->successMessage = "Elementos copiados con éxito a '{$this->bulkDestDirectory}'.";
            $this->selectedItems = [];
            $this->showBulkCopyModal = false;
            $this->bulkDestDirectory = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(FileService $fileService, MonitoringService $monitoringService)
    {
        try {
            $items = $fileService->listDirectory($this->currentPath);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->currentPath = '';
            $items = $fileService->listDirectory($this->currentPath);
        }

        // Generate breadcrumb links
        $breadcrumbs = [];
        $accumulated = '';
        $parts = array_filter(explode('/', $this->currentPath));
        foreach ($parts as $part) {
            $accumulated .= '/' . $part;
            $breadcrumbs[] = [
                'name' => $part,
                'path' => $accumulated
            ];
        }

        $snapshot = $monitoringService->snapshot();
        $diskInfo = $snapshot['disk'] ?? ['usage' => 0, 'total' => 0, 'used' => 0, 'free' => 0];

        return view('livewire.files.file-manager', [
            'items' => $items,
            'breadcrumbs' => $breadcrumbs,
            'diskInfo' => $diskInfo,
        ])->layout('layouts.app', [
            'title'      => 'Administrador de Archivos',
            'breadcrumb' => '<span>Hosting</span> / <strong>Administrador de Archivos</strong>',
        ]);
    }
}

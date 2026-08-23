<?php

namespace App\Livewire\Files;

use App\Services\FileService;
use App\Services\MonitoringService;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileManager extends Component
{
    use WithFileUploads;

    public string $currentPath = ''; // Relative to the user's webroot
    public string $serverLabel = 'VPS';
    
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

    public bool $showZipModal = false;
    public string $zipFileName = '';

    public ?string $renamingPath = null;
    public string $newName = '';

    public ?string $chmodPath = null;
    public string $chmodOctal = '';

    public ?string $editingPath = null;

    // Success/error alerts
    public string $successMessage = '';
    public string $errorMessage = '';

    // Delete Modal
    public bool $showDeleteModal = false;
    public string $deletingItemName = '';
    public bool $isDeletingMultiple = false;

    // Tree state
    public array $expandedPaths = ['']; // root is always expanded

    public function mount(): void
    {
        $this->currentPath = '';
        $this->selectedItems = [];
        $this->expandedPaths = [''];
        $this->serverLabel = config('larapanel.server_label', 'VPS');
    }

    public function navigate(string $path): void
    {
        $this->currentPath = $path;
        $this->selectedItems = [];
        $this->resetModals();
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
        $this->resetModals();
        $this->resetErrorAlerts();
    }

    protected function resetModals(): void
    {
        $this->showCreateFolderModal = false;
        $this->newFolderName = '';
        $this->showCreateFileModal = false;
        $this->newFileName = '';
        $this->showZipModal = false;
        $this->zipFileName = '';
        $this->showBulkMoveModal = false;
        $this->showBulkCopyModal = false;
        $this->bulkDestDirectory = '';
        $this->showDeleteModal = false;
        $this->deletingItemName = '';
        $this->isDeletingMultiple = false;
        $this->renamingPath = null;
        $this->newName = '';
        $this->chmodPath = null;
        $this->chmodOctal = '';
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
            if (! $fileService->createFolder($this->currentPath, $this->newFolderName)) {
                throw new \RuntimeException('No se pudo crear la carpeta.');
            }
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
            if (! $fileService->createFile($this->currentPath, $this->newFileName)) {
                throw new \RuntimeException('No se pudo crear el archivo.');
            }
            $this->successMessage = "Archivo '{$this->newFileName}' creado con éxito.";
            $this->showCreateFileModal = false;
            $this->newFileName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirmDelete(string $name = ''): void
    {
        if (empty($name) && empty($this->selectedItems)) {
            return;
        }

        $this->deletingItemName = $name;
        $this->isDeletingMultiple = empty($name);
        $this->showDeleteModal = true;
    }

    public function executeDelete(FileService $fileService): void
    {
        if ($this->isDeletingMultiple) {
            $this->deleteSelected($fileService);
        } else {
            $this->deleteItem($this->deletingItemName, $fileService);
        }
        $this->showDeleteModal = false;
        $this->deletingItemName = '';
        $this->selectedItems = [];
    }

    /**
     * Delete resource.
     */
    public function deleteItem(string $name, FileService $fileService): void
    {
        $path = $this->currentPath . '/' . $name;
        try {
            $fileService->delete($path);
            $this->selectedItems = array_values(array_diff($this->selectedItems, [$name]));
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
            if (! $fileService->rename($this->renamingPath, $this->newName)) {
                throw new \RuntimeException('No se pudo renombrar el recurso.');
            }
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
            if (! $fileService->chmod($this->chmodPath, $this->chmodOctal)) {
                throw new \RuntimeException('No se pudieron cambiar los permisos.');
            }
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
            $content = $fileService->getFileContent($this->editingPath);
            $this->resetErrorAlerts();
            $this->dispatch('open-editor', content: $content, filename: $name);
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
            if (! $fileService->updateFileContent($this->editingPath, $content)) {
                throw new \RuntimeException('No se pudo guardar el archivo.');
            }
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
        \Illuminate\Support\Facades\Log::info("startUnzip called for: " . $name);
        $this->unzipItemName = $name;
        $this->showUnzipModal = true;
    }

    public function processUnzip(FileService $fileService): void
    {
        $path = $this->currentPath . '/' . $this->unzipItemName;
        \Illuminate\Support\Facades\Log::info("processUnzip triggered for: " . $path);
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
        $allowedExtensions = [
            'txt', 'log', 'md', 'csv', 'xml', 'json', 'yaml', 'yml',
            'php', 'phtml', 'php5', 'php7', 'php8', 'inc',
            'js', 'ts', 'jsx', 'tsx', 'css', 'scss', 'less',
            'html', 'htm', 'shtml', 'xhtml',
            'htaccess', 'htpasswd', 'env', 'ini', 'conf', 'cfg',
            'sh', 'bash', 'zsh', 'py', 'rb', 'pl',
            'sql', 'db', 'sqlite', 'sqlite3',
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp', 'avif',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'tar', 'gz', 'bz2', 'xz', 'rar', '7z',
            'mp3', 'mp4', 'avi', 'mov', 'mkv', 'webm', 'ogg', 'wav',
            'ttf', 'otf', 'woff', 'woff2', 'eot',
        ];

        $this->validate([
            'uploads.*' => 'file|max:2048000',
        ]);

        try {
            foreach ($this->uploads as $upload) {
                $originalName = $upload->getClientOriginalName();
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions) && $extension !== '') {
                    throw new \RuntimeException(
                        "Extensión '.{$extension}' no permitida. Archivo: {$originalName}"
                    );
                }

                $filename = preg_replace('/[^\w\-\.]/', '_', $originalName);
                $filename = preg_replace('/_+/', '_', $filename);
                $filename = trim($filename, '_.');

                if ($filename === '' || $filename === '.') {
                    throw new \RuntimeException("Nombre de archivo no válido: {$originalName}");
                }

                $tmpPath = $upload->storeAs('livewire-tmp', $filename);
                $fullTmpPath = \Illuminate\Support\Facades\Storage::disk('local')->path($tmpPath);
                
                $destPath = $fileService->resolvePath($this->currentPath . '/' . $filename);
                
                if (PHP_OS_FAMILY !== 'Windows') {
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
            'zipFileName' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_\-\.]+$/',
        ]);

        $zipName = $this->zipFileName;
        if (!str_ends_with(strtolower($zipName), '.zip')) {
            $zipName .= '.zip';
        }

        try {
            $zipPath = $this->currentPath . '/' . $zipName;
            
            if (count($this->selectedItems) === 1) {
                $fileService->zip($this->currentPath . '/' . $this->selectedItems[0], $zipName);
            } else {
                $fileService->zipMultiple(
                    array_map(fn ($item) => $this->currentPath . '/' . $item, $this->selectedItems),
                    $zipPath,
                );
            }

            $this->successMessage = "Comprimido como {$zipName} con éxito.";
            $this->selectedItems = [];
            $this->showZipModal = false;
            $this->zipFileName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function prepareZip(?string $name = null): void
    {
        if ($name !== null) {
            $this->selectedItems = [$name];
            $this->zipFileName = $name . '.zip';
        } elseif (empty($this->selectedItems)) {
            return;
        } else {
            $this->zipFileName = 'archivo_comprimido.zip';
        }

        $this->showZipModal = true;
    }

    public function prepareBulkMove(): void
    {
        if (! empty($this->selectedItems)) {
            $this->bulkDestDirectory = '';
            $this->showBulkMoveModal = true;
        }
    }

    public function prepareBulkCopy(): void
    {
        if (! empty($this->selectedItems)) {
            $this->bulkDestDirectory = '';
            $this->showBulkCopyModal = true;
        }
    }

    public function closeCreateFolderModal(): void
    {
        $this->showCreateFolderModal = false;
        $this->newFolderName = '';
    }

    public function closeZipModal(): void
    {
        $this->showZipModal = false;
        $this->zipFileName = '';
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

    public function toggleNode(string $path): void
    {
        $index = array_search($path, $this->expandedPaths);
        if ($index !== false) {
            unset($this->expandedPaths[$index]);
            $this->expandedPaths = array_values($this->expandedPaths);
        } else {
            $this->expandedPaths[] = $path;
        }
    }

    protected function buildTreeLevel(string $path, FileService $fileService, int $depth = 0): array
    {
        $maxDepth = 6;
        $tree = [];
        try {
            $items = $fileService->listDirectory($path);
        } catch (\Throwable $e) {
            return []; 
        }

        foreach ($items as $item) {
            if ($item['is_dir']) {
                $itemPath = $path === '' ? $item['name'] : $path . '/' . $item['name'];
                $node = [
                    'name' => $item['name'],
                    'path' => $itemPath,
                    'isExpanded' => in_array($itemPath, $this->expandedPaths),
                    'children' => []
                ];

                if ($node['isExpanded'] && $depth < $maxDepth) {
                    $node['children'] = $this->buildTreeLevel($itemPath, $fileService, $depth + 1);
                }

                $tree[] = $node;
            }
        }
        usort($tree, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $tree;
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

        $tree = $this->buildTreeLevel('', $fileService, 0);

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

        try {
            $partitions = $monitoringService->getDiskMetrics();
            $disk = collect($partitions)->firstWhere('mount', '/') ?? ($partitions[0] ?? null);
            $diskInfo = $disk ? [
                'usage' => $disk['percent'],
                'total' => $disk['size'],
                'used' => $disk['used'],
                'free' => $disk['free'],
            ] : ['usage' => 0, 'total' => 0, 'used' => 0, 'free' => 0];
        } catch (\Throwable) {
            $diskInfo = ['usage' => 0, 'total' => 0, 'used' => 0, 'free' => 0];
        }

        return view('livewire.files.file-manager', [
            'items' => $items,
            'breadcrumbs' => $breadcrumbs,
            'diskInfo' => $diskInfo,
            'tree' => $tree,
        ])->layout('layouts.app', [
            'title'      => 'Administrador de Archivos',
            'breadcrumb' => '<span>Hosting</span> / <strong>Administrador de Archivos</strong>',
        ]);
    }
}

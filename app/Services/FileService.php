<?php

namespace App\Services;

use App\Shell\SudoExecutor;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class FileService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Get base directory path depending on env.
     */
    public function getRootPath(): string
    {
        if (!app()->isProduction()) {
            $devPath = storage_path('app/public/webroot');
            if (!file_exists($devPath)) {
                @mkdir($devPath, 0755, true);
                @file_put_contents($devPath . '/index.html', '<html><body><h1>LaraPanel Dev Webroot</h1></body></html>');
            }
            return $devPath;
        }
        return config('larapanel.paths.webroots', '/var/www');
    }

    /**
     * Safely resolve absolute path and prevent traversal attacks.
     */
    public function resolvePath(string $relativePath): string
    {
        $root = realpath($this->getRootPath());
        if (!$root) {
            // fallback in case realpath fails
            $root = $this->getRootPath();
        }

        $path = $root . '/' . ltrim($relativePath, '/');

        // Resolve absolute path parts to catch navigation like "/../"
        $resolved = $this->normalizePath($path);

        if (!str_starts_with($resolved, $root)) {
            throw new \InvalidArgumentException("Acceso no autorizado: Intento de escape del directorio raíz.");
        }

        return $resolved;
    }

    /**
     * Normalize path helper to handle dots (../ or ./) on non-existent files.
     */
    protected function normalizePath(string $path): string
    {
        $parts = array_filter(explode('/', str_replace('\\', '/', $path)), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return (str_starts_with($path, '/') ? '/' : '') . implode('/', $absolutes);
    }

    /**
     * List files and folders.
     */
    public function listDirectory(string $relativePath): array
    {
        $absolutePath = $this->resolvePath($relativePath);
        if (!is_dir($absolutePath)) {
            throw new \InvalidArgumentException("El directorio especificado no existe.");
        }

        $files = [];
        $items = scandir($absolutePath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $absolutePath . '/' . $item;
            $isDir = is_dir($itemPath);
            
            // Resolver owner y group si la extensión posix está instalada
            $ownerId = @fileowner($itemPath);
            $groupId = @filegroup($itemPath);
            $owner = function_exists('posix_getpwuid') && $ownerId !== false ? (@posix_getpwuid($ownerId)['name'] ?? $ownerId) : $ownerId;
            $group = function_exists('posix_getgrgid') && $groupId !== false ? (@posix_getgrgid($groupId)['name'] ?? $groupId) : $groupId;

            $files[] = [
                'name' => $item,
                'is_dir' => $isDir,
                'size' => $isDir ? 0 : filesize($itemPath),
                'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                'owner' => $owner ?: 'unknown',
                'group' => $group ?: 'unknown',
                'updated_at' => filemtime($itemPath),
                'mime' => $isDir ? 'directory' : (mime_content_type($itemPath) ?: 'application/octet-stream'),
            ];
        }

        // Sort: directories first, then files alphabetically
        usort($files, function ($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        return $files;
    }

    /**
     * Create folder.
     */
    public function createFolder(string $parentPath, string $name): bool
    {
        $targetPath = $this->resolvePath($parentPath . '/' . $name);
        
        if (file_exists($targetPath)) {
            throw new \RuntimeException("La carpeta ya existe.");
        }

        AuditLog::record('filemanager.folder.create', $parentPath . '/' . $name);

        if (!app()->isProduction()) {
            return mkdir($targetPath, 0755, true);
        }

        try {
            $this->sudo->run(['mkdir', '-p', $targetPath]);
            $this->sudo->run(['chown', 'www-data:www-data', $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to create folder {$targetPath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create empty file.
     */
    public function createFile(string $parentPath, string $name): bool
    {
        $targetPath = $this->resolvePath($parentPath . '/' . $name);
        
        if (file_exists($targetPath)) {
            throw new \RuntimeException("El archivo ya existe.");
        }

        AuditLog::record('filemanager.file.create', $parentPath . '/' . $name);

        if (!app()->isProduction()) {
            return touch($targetPath);
        }

        try {
            $this->sudo->run(['touch', $targetPath]);
            $this->sudo->run(['chown', 'www-data:www-data', $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to create file {$targetPath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete file or directory.
     */
    public function delete(string $relativePath): bool
    {
        $targetPath = $this->resolvePath($relativePath);
        if (!file_exists($targetPath)) {
            throw new \RuntimeException("El recurso no existe.");
        }

        AuditLog::record('filemanager.delete', $relativePath);

        if (!app()->isProduction()) {
            return $this->deleteRecursive($targetPath);
        }

        try {
            $this->sudo->run(['rm', '-rf', $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to delete {$targetPath}: " . $e->getMessage());
            return false;
        }
    }

    protected function deleteRecursive(string $path): bool
    {
        if (is_dir($path)) {
            $items = array_diff(scandir($path), ['.', '..']);
            foreach ($items as $item) {
                $this->deleteRecursive($path . '/' . $item);
            }
            return rmdir($path);
        }
        return unlink($path);
    }

    /**
     * Rename file/folder.
     */
    public function rename(string $relativePath, string $newName): bool
    {
        $oldPath = $this->resolvePath($relativePath);
        $parent = dirname($relativePath);
        $newPath = $this->resolvePath($parent . '/' . $newName);

        if (!file_exists($oldPath)) {
            throw new \RuntimeException("El recurso original no existe.");
        }
        if (file_exists($newPath)) {
            throw new \RuntimeException("Ya existe un recurso con el nombre destino.");
        }

        AuditLog::record('filemanager.rename', $relativePath, ['new_name' => $newName]);

        if (!app()->isProduction()) {
            return rename($oldPath, $newPath);
        }

        try {
            $this->sudo->run(['mv', $oldPath, $newPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to rename {$oldPath} to {$newPath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read file contents.
     */
    public function getFileContent(string $relativePath): string
    {
        $targetPath = $this->resolvePath($relativePath);
        if (!file_exists($targetPath) || is_dir($targetPath)) {
            throw new \RuntimeException("Archivo no válido para lectura.");
        }
        
        // Limit max readable size for safety (e.g. 5MB)
        if (filesize($targetPath) > 5 * 1024 * 1024) {
            throw new \RuntimeException("El archivo supera el tamaño máximo permitido para edición (5MB).");
        }

        return file_get_contents($targetPath);
    }

    /**
     * Write file contents.
     */
    public function updateFileContent(string $relativePath, string $content): bool
    {
        $targetPath = $this->resolvePath($relativePath);
        if (is_dir($targetPath)) {
            throw new \RuntimeException("La ruta destino es un directorio.");
        }

        AuditLog::record('filemanager.file.write', $relativePath);

        if (!app()->isProduction()) {
            return file_put_contents($targetPath, $content) !== false;
        }

        try {
            $tmpFile = tempnam('/tmp', 'lp_edit_');
            file_put_contents($tmpFile, $content);
            $this->sudo->run(['cp', $tmpFile, $targetPath]);
            $this->sudo->run(['chown', 'www-data:www-data', $targetPath]);
            unlink($tmpFile);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to write to {$targetPath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change permissions (Chmod).
     */
    public function chmod(string $relativePath, string $octal): bool
    {
        $targetPath = $this->resolvePath($relativePath);
        if (!file_exists($targetPath)) {
            throw new \RuntimeException("El recurso no existe.");
        }

        if (!preg_match('/^[0-7]{3,4}$/', $octal)) {
            throw new \InvalidArgumentException("Formato de permisos octales inválido (ej. 0755).");
        }

        AuditLog::record('filemanager.chmod', $relativePath, ['mode' => $octal]);

        if (!app()->isProduction()) {
            return chmod($targetPath, octdec($octal));
        }

        try {
            $this->sudo->run(['chmod', $octal, $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to chmod {$targetPath} to {$octal}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Zip a folder/file.
     */
    public function zip(string $relativePath, string $zipName): bool
    {
        $targetPath = $this->resolvePath($relativePath);
        $parent = dirname($targetPath);
        $zipPath = $this->resolvePath(dirname($relativePath) . '/' . $zipName);

        if (!file_exists($targetPath)) {
            throw new \RuntimeException("El recurso a comprimir no existe.");
        }

        AuditLog::record('filemanager.zip', $relativePath, ['archive' => $zipName]);

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                if (is_dir($targetPath)) {
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($targetPath),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePathInZip = substr($filePath, strlen($targetPath) + 1);
                            $zip->addFile($filePath, $relativePathInZip);
                        }
                    }
                } else {
                    $zip->addFile($targetPath, basename($targetPath));
                }
                $zip->close();
                return true;
            }
        }
        return false;
    }

    /**
     * Unzip archive.
     */
    public function unzip(string $relativePath): bool
    {
        $zipPath = $this->resolvePath($relativePath);
        $destPath = dirname($zipPath);

        if (!file_exists($zipPath)) {
            throw new \RuntimeException("El archivo zip no existe.");
        }

        AuditLog::record('filemanager.unzip', $relativePath);

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($destPath);
                $zip->close();
                return true;
            }
        }
        return false;
    }

    /**
     * Move file/folder to a new parent directory.
     */
    public function move(string $relativeSource, string $relativeDestParent): bool
    {
        $source = $this->resolvePath($relativeSource);
        $name = basename($relativeSource);
        $dest = $this->resolvePath($relativeDestParent . '/' . $name);

        if (!file_exists($source)) {
            throw new \RuntimeException("El recurso origen no existe.");
        }
        if (file_exists($dest)) {
            throw new \RuntimeException("El recurso destino ya existe en '{$relativeDestParent}'.");
        }

        AuditLog::record('filemanager.move', $relativeSource, ['dest' => $relativeDestParent]);

        if (!app()->isProduction()) {
            return rename($source, $dest);
        }

        try {
            $this->sudo->run(['mv', $source, $dest]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to move {$source} to {$dest}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy file/folder to a new parent directory.
     */
    public function copy(string $relativeSource, string $relativeDestParent): bool
    {
        $source = $this->resolvePath($relativeSource);
        $name = basename($relativeSource);
        $dest = $this->resolvePath($relativeDestParent . '/' . $name);

        if (!file_exists($source)) {
            throw new \RuntimeException("El recurso origen no existe.");
        }
        if (file_exists($dest)) {
            throw new \RuntimeException("El recurso destino ya existe en '{$relativeDestParent}'.");
        }

        AuditLog::record('filemanager.copy', $relativeSource, ['dest' => $relativeDestParent]);

        if (!app()->isProduction()) {
            if (is_dir($source)) {
                return $this->copyRecursive($source, $dest);
            }
            return copy($source, $dest);
        }

        try {
            if (is_dir($source)) {
                $this->sudo->run(['cp', '-r', $source, $dest]);
            } else {
                $this->sudo->run(['cp', $source, $dest]);
            }
            $this->sudo->run(['chown', '-R', 'www-data:www-data', $dest]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to copy {$source} to {$dest}: " . $e->getMessage());
            return false;
        }
    }

    protected function copyRecursive(string $src, string $dst): bool
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copyRecursive($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * Delete multiple files/folders.
     */
    public function deleteMultiple(array $relativePaths): void
    {
        foreach ($relativePaths as $path) {
            $this->delete($path);
        }
    }

    /**
     * Move multiple files/folders.
     */
    public function moveMultiple(array $relativePaths, string $destParent): void
    {
        foreach ($relativePaths as $path) {
            $this->move($path, $destParent);
        }
    }

    /**
     * Copy multiple files/folders.
     */
    public function copyMultiple(array $relativePaths, string $destParent): void
    {
        foreach ($relativePaths as $path) {
            $this->copy($path, $destParent);
        }
    }
}

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
     * Get base directory path depending on system environment.
     * If /var/www (or configured webroot) exists, use it. Otherwise fallback to local dev path.
     */
    public function getRootPath(): string
    {
        $customRoot = config('larapanel.paths.webroots', '/var/www');
        if (is_dir($customRoot)) {
            return $customRoot;
        }

        $devPath = storage_path('app/public/webroot');
        if (!file_exists($devPath)) {
            @mkdir($devPath, 0755, true);
            @file_put_contents($devPath . '/index.html', '<html><body><h1>LaraPanel Dev Webroot</h1></body></html>');
        }
        return $devPath;
    }

    /**
     * Safely resolve absolute path and prevent traversal attacks.
     */
    public function resolvePath(string $relativePath): string
    {
        $root = realpath($this->getRootPath());
        if ($root === false || ! is_dir($root)) {
            throw new \RuntimeException('La raíz de archivos no está disponible.');
        }

        $normalizedRoot = $this->normalizePath($root);
        $path = $normalizedRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

        // Resolve absolute path parts to catch navigation like "/../"
        $resolved = $this->normalizePath($path);

        if ($resolved === $normalizedRoot) {
            return $resolved;
        }

        // Resolve existing targets and the nearest existing parent so a
        // symlink cannot point an operation outside the configured webroot.
        $existing = realpath($resolved);
        if ($existing !== false) {
            $this->assertInsideRoot($normalizedRoot, $existing);
            // Keep the lexical path so deleting a symlink removes the link,
            // not the target it points to.
            return $resolved;
        }

        $missing = [];
        $parent = $resolved;
        while ($parent !== dirname($parent) && ! file_exists($parent)) {
            array_unshift($missing, basename($parent));
            $parent = dirname($parent);
        }

        $realParent = realpath($parent);
        if ($realParent === false) {
            throw new \InvalidArgumentException('La ruta padre no existe.');
        }
        $this->assertInsideRoot($normalizedRoot, $realParent);

        $candidate = $this->normalizePath($realParent . '/' . implode('/', $missing));
        $this->assertInsideRoot($normalizedRoot, $candidate);

        return $resolved;
    }

    protected function assertInsideRoot(string $root, string $path): void
    {
        $root = rtrim($this->normalizePath($root), '/') . '/';
        $path = rtrim($this->normalizePath($path), '/') . '/';

        if (! str_starts_with($path, $root) && rtrim($path, '/') !== rtrim($root, '/')) {
            throw new \InvalidArgumentException("Acceso no autorizado: Intento de escape del directorio raíz.");
        }
    }

    protected function assertNotRoot(string $relativePath): void
    {
        if (trim(str_replace(['/', '\\', '.'], '', $relativePath)) === '') {
            throw new \InvalidArgumentException('La raíz del explorador no se puede modificar.');
        }
    }

    protected function assertSafeArchiveName(string $name): void
    {
        $name = str_replace('\\', '/', $name);
        if ($name === '' || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:\//', $name)) {
            throw new \InvalidArgumentException('El nombre del archivo no es válido.');
        }
        foreach (explode('/', $name) as $part) {
            if ($part === '..') {
                throw new \InvalidArgumentException('La ruta del archivo no es segura.');
            }
        }
    }

    protected function assertSafeZipEntries(\ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:\//', $name)) {
                throw new \RuntimeException('El ZIP contiene una ruta absoluta no segura.');
            }
            foreach (explode('/', $name) as $part) {
                if ($part === '..') {
                    throw new \RuntimeException('El ZIP contiene una ruta que escapa del destino.');
                }
            }
        }
    }

    /**
     * Normalize path helper to handle dots (../ or ./) on non-existent files.
     */
    protected function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($normalized, '/');
        $drivePrefix = '';

        if (preg_match('/^[a-zA-Z]:/', $normalized, $matches)) {
            $drivePrefix = $matches[0];
            $normalized = substr($normalized, strlen($drivePrefix));
            $isAbsolute = true;
        }

        $parts = array_filter(explode('/', $normalized), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' === $part) continue;
            if ('..' === $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        return $drivePrefix . ($isAbsolute ? '/' : '') . implode('/', $absolutes);
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
        $items = @scandir($absolutePath);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $absolutePath . '/' . $item;
            $isDir = @is_dir($itemPath);
            
            // Resolver owner y group si la extensión posix está instalada
            $ownerId = @fileowner($itemPath);
            $groupId = @filegroup($itemPath);
            $owner = function_exists('posix_getpwuid') && $ownerId !== false ? (@posix_getpwuid($ownerId)['name'] ?? $ownerId) : $ownerId;
            $group = function_exists('posix_getgrgid') && $groupId !== false ? (@posix_getgrgid($groupId)['name'] ?? $groupId) : $groupId;

            $perms = @fileperms($itemPath);
            $permissions = $perms !== false ? substr(sprintf('%o', $perms), -4) : '0755';

            $files[] = [
                'name' => $item,
                'is_dir' => $isDir,
                'size' => $isDir ? 0 : (@filesize($itemPath) ?: 0),
                'permissions' => $permissions,
                'owner' => $owner ?: 'unknown',
                'group' => $group ?: 'unknown',
                'updated_at' => @filemtime($itemPath) ?: time(),
                'mime' => $isDir ? 'directory' : (@mime_content_type($itemPath) ?: 'application/octet-stream'),
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

        if (PHP_OS_FAMILY === 'Windows') {
            return mkdir($targetPath, 0755, true);
        }

        try {
            $this->sudo->run(['mkdir', '-p', $targetPath]);
            $this->sudo->run(['chown', 'www-data:www-data', $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to create folder {$targetPath}: " . $e->getMessage());
            throw $e;
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

        if (PHP_OS_FAMILY === 'Windows') {
            return touch($targetPath);
        }

        try {
            $this->sudo->run(['touch', $targetPath]);
            $this->sudo->run(['chown', 'www-data:www-data', $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to create file {$targetPath}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete file or directory.
     */
    public function delete(string $relativePath): bool
    {
        $this->assertNotRoot($relativePath);
        $targetPath = $this->resolvePath($relativePath);
        if (!file_exists($targetPath)) {
            throw new \RuntimeException("El recurso no existe.");
        }

        AuditLog::record('filemanager.delete', $relativePath);

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->deleteRecursive($targetPath);
        }

        try {
            $this->sudo->run(['rm', '-rf', $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to delete {$targetPath}: " . $e->getMessage());
            throw $e;
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
        $this->assertNotRoot($relativePath);
        $this->assertSafeArchiveName($newName);
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

        if (PHP_OS_FAMILY === 'Windows') {
            return rename($oldPath, $newPath);
        }

        try {
            $this->sudo->run(['mv', $oldPath, $newPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to rename {$oldPath} to {$newPath}: " . $e->getMessage());
            throw $e;
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

        if (PHP_OS_FAMILY === 'Windows') {
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
            throw $e;
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

        if (PHP_OS_FAMILY === 'Windows') {
            return chmod($targetPath, octdec($octal));
        }

        try {
            $this->sudo->run(['chmod', $octal, $targetPath]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to chmod {$targetPath} to {$octal}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Zip a folder/file.
     */
    public function zip(string $relativePath, string $zipName): bool
    {
        $this->assertNotRoot($relativePath);
        $this->assertSafeArchiveName($zipName);
        $targetPath = $this->resolvePath($relativePath);
        $zipPath = $this->resolvePath(dirname($relativePath) . '/' . $zipName);

        if (!file_exists($targetPath)) {
            throw new \RuntimeException("El recurso a comprimir no existe.");
        }

        AuditLog::record('filemanager.zip', $relativePath, ['archive' => $zipName]);

        if (class_exists(\ZipArchive::class)) {
            $tempPath = tempnam(storage_path('app'), 'lp_zip_');
            if ($tempPath === false) {
                throw new \RuntimeException('No se pudo crear el archivo temporal para el ZIP.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
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

                if (PHP_OS_FAMILY === 'Windows') {
                    if (! rename($tempPath, $zipPath)) {
                        throw new \RuntimeException('No se pudo guardar el archivo ZIP.');
                    }
                } else {
                    $this->sudo->run(['cp', $tempPath, $zipPath]);
                    $this->sudo->run(['chown', 'www-data:www-data', $zipPath]);
                    @unlink($tempPath);
                }
                return true;
            }
            @unlink($tempPath);
        }
        throw new \RuntimeException('La extensión PHP ZipArchive no está disponible o no se pudo crear el ZIP.');
    }

    public function zipMultiple(array $relativePaths, string $zipRelativePath): bool
    {
        if ($relativePaths === []) {
            throw new \InvalidArgumentException('No hay elementos seleccionados para comprimir.');
        }

        $this->assertSafeArchiveName(basename($zipRelativePath));
        $zipPath = $this->resolvePath($zipRelativePath);
        $items = [];
        foreach ($relativePaths as $relativePath) {
            $this->assertNotRoot($relativePath);
            $path = $this->resolvePath($relativePath);
            if (! file_exists($path)) {
                throw new \RuntimeException("El recurso '{$relativePath}' no existe.");
            }
            $items[] = [$relativePath, $path];
        }

        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP ZipArchive no está instalada.');
        }

        $tempPath = tempnam(storage_path('app'), 'lp_zip_');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal para el ZIP.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('No se pudo crear el archivo ZIP.');
        }

        foreach ($items as [$relativePath, $path]) {
            $entryRoot = basename($relativePath);
            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($iterator as $file) {
                    if ($file->isDir()) continue;
                    $filePath = $file->getRealPath();
                    $entry = $entryRoot . '/' . substr($filePath, strlen($path) + 1);
                    $zip->addFile($filePath, str_replace('\\', '/', $entry));
                }
            } else {
                $zip->addFile($path, $entryRoot);
            }
        }
        $zip->close();

        if (PHP_OS_FAMILY === 'Windows') {
            if (! rename($tempPath, $zipPath)) {
                throw new \RuntimeException('No se pudo guardar el archivo ZIP.');
            }
        } else {
            $this->sudo->run(['cp', $tempPath, $zipPath]);
            $this->sudo->run(['chown', 'www-data:www-data', $zipPath]);
            @unlink($tempPath);
        }

        AuditLog::record('filemanager.zip_multiple', $zipRelativePath, ['items' => $relativePaths]);
        return true;
    }

    /**
     * Unzip archive with progress stream.
     */
    public function unzipStream(string $relativePath, callable $onProgress): bool
    {
        $zipPath = $this->resolvePath($relativePath);
        $destPath = dirname($zipPath);

        if (!file_exists($zipPath)) {
            throw new \RuntimeException("El archivo zip no existe.");
        }

        AuditLog::record('filemanager.unzip_stream', $relativePath);

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $this->assertSafeZipEntries($zip);
                $total = $zip->numFiles;

                if (! is_writable($destPath) && PHP_OS_FAMILY !== 'Windows') {
                    $result = $this->sudo->run(['unzip', '-o', $zipPath, '-d', $destPath], false);
                    $zip->close();
                    if ($result->failed()) {
                        throw new \RuntimeException('No se pudo extraer el archivo ZIP: ' . $result->stderr);
                    }
                    $onProgress('Extracción completada', $total, $total);
                    $this->sudo->run(['chown', '-R', 'www-data:www-data', $destPath]);
                    return true;
                }
                
                // Extraer de a grupos pequeños o uno por uno
                for ($i = 0; $i < $total; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (! $zip->extractTo($destPath, $filename)) {
                        throw new \RuntimeException("No se pudo extraer '{$filename}'.");
                    }
                    
                    // Si el callback devuelve false, abortamos (útil para errores)
                    if ($onProgress($filename, $i + 1, $total) === false) {
                        break;
                    }
                }
                $zip->close();
                
                if (PHP_OS_FAMILY !== 'Windows') {
                    try {
                        $this->sudo->run(['chown', '-R', 'www-data:www-data', $destPath]);
                    } catch (\Throwable $e) {}
                }
                return true;
            }
            throw new \RuntimeException("No se pudo abrir el archivo zip.");
        }
        
        throw new \RuntimeException("La extensión PHP ZipArchive no está instalada en el servidor.");
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

        if (PHP_OS_FAMILY === 'Windows') {
            return rename($source, $dest);
        }

        try {
            $this->sudo->run(['mv', $source, $dest]);
            return true;
        } catch (\Throwable $e) {
            Log::error("FileManager: Failed to move {$source} to {$dest}: " . $e->getMessage());
            throw $e;
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

        if (PHP_OS_FAMILY === 'Windows') {
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
            throw $e;
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

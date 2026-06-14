<?php

namespace App\Services;

use App\Models\DatabaseInstance;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

class DatabaseService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Create database schema and MySQL user, grant permissions.
     */
    public function create(User $user, array $data): DatabaseInstance
    {
        $dbName  = trim($data['db_name']);
        $dbUser  = trim($data['db_user']);
        $dbPass  = $data['db_password'];
        $domainId= $data['domain_id'] ?? null;
        $displayName = $data['display_name'] ?? $dbName;

        // Strict validation to prevent query manipulation at the DB layer
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $dbName)) {
            throw new \InvalidArgumentException('El nombre de la base de datos debe ser alfanumérico y de 3 a 32 caracteres.');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $dbUser)) {
            throw new \InvalidArgumentException('El nombre de usuario de la base de datos debe ser alfanumérico y de 3 a 32 caracteres.');
        }

        // 1. Create DB and user on MySQL server
        if (app()->isProduction()) {
            $queries = [
                "CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
                "CREATE USER '{$dbUser}'@'%' IDENTIFIED BY '{$dbPass}';",
                "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'%';",
                "FLUSH PRIVILEGES;"
            ];

            foreach ($queries as $q) {
                $this->sudo->run(['mysql', '-e', $q]);
            }
        }

        // 2. Persist to LaraPanel database
        $instance = DatabaseInstance::create([
            'user_id' => $user->id,
            'domain_id' => $domainId,
            'db_name' => $dbName,
            'display_name' => $displayName,
            'db_user' => $dbUser,
            'db_password_hint' => substr($dbPass, 0, 3) . '***' . substr($dbPass, -2),
            'db_host' => 'localhost',
            'db_port' => 3306,
            'engine' => 'mysql',
            'size_bytes' => 1024 * 1024 * 2, // 2MB default simulated size
        ]);

        AuditLog::record('database.created', $dbName, ['instance_id' => $instance->id]);

        return $instance;
    }

    /**
     * Delete database schema and MySQL user.
     */
    public function delete(DatabaseInstance $instance): void
    {
        $dbName = $instance->db_name;
        $dbUser = $instance->db_user;

        // Validate to be safe
        if (!preg_match('/^[a-zA-Z0-9_]{3,64}$/', $dbName) || !preg_match('/^[a-zA-Z0-9_]{3,64}$/', $dbUser)) {
            throw new \RuntimeException('Falla de seguridad: nombre de base de datos o usuario inválidos.');
        }

        if (app()->isProduction()) {
            $queries = [
                "DROP DATABASE IF EXISTS `{$dbName}`;",
                "DROP USER IF EXISTS '{$dbUser}'@'%';",
                "FLUSH PRIVILEGES;"
            ];

            foreach ($queries as $q) {
                $this->sudo->run(['mysql', '-e', $q], checkExit: false);
            }
        }

        AuditLog::record('database.deleted', $dbName, ['db_user' => $dbUser]);
        
        $instance->forceDelete();
    }

    /**
     * Change MySQL user password.
     */
    public function changePassword(DatabaseInstance $instance, string $newPassword): void
    {
        $dbUser = $instance->db_user;

        if (!preg_match('/^[a-zA-Z0-9_]{3,64}$/', $dbUser)) {
            throw new \RuntimeException('Falla de seguridad: nombre de usuario inválido.');
        }

        if (app()->isProduction()) {
            $query = "ALTER USER '{$dbUser}'@'%' IDENTIFIED BY '{$newPassword}'; FLUSH PRIVILEGES;";
            $this->sudo->run(['mysql', '-e', $query]);
        }

        $instance->update([
            'db_password_hint' => substr($newPassword, 0, 3) . '***' . substr($newPassword, -2),
        ]);

        AuditLog::record('database.password.changed', $instance->db_name);
    }

    /**
     * Fetch real size from MySQL.
     */
    public function updateSize(DatabaseInstance $instance): int
    {
        if (!app()->isProduction()) {
            return $instance->size_bytes;
        }

        $dbName = $instance->db_name;
        $q = "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '{$dbName}'";
        
        try {
            $result = $this->sudo->run(['mysql', '-sN', '-e', $q]);
            $size = (int) trim($result->stdout);
            
            $instance->update(['size_bytes' => $size]);
            return $size;
        } catch (\Throwable $e) {
            Log::error("Failed to fetch size for database {$dbName}: " . $e->getMessage());
            return $instance->size_bytes;
        }
    }

    /**
     * Export database to SQL dump file.
     * Returns absolute path to the dump file for download.
     */
    public function exportDump(DatabaseInstance $instance): string
    {
        $dbName = $instance->db_name;

        if (!preg_match('/^[a-zA-Z0-9_]{3,64}$/', $dbName)) {
            throw new \RuntimeException('Nombre de base de datos inválido para exportación.');
        }

        $dumpDir = storage_path('app/public/db_exports');
        if (!is_dir($dumpDir)) {
            @mkdir($dumpDir, 0700, true);
        }

        $filename = $dbName . '_' . now()->format('Ymd_His') . '.sql';
        $dumpPath = $dumpDir . '/' . $filename;

        if (!app()->isProduction()) {
            $sql  = "-- LaraPanel Database Export: {$dbName}\n";
            $sql .= "-- Generated: " . now()->toDateTimeString() . "\n\n";
            $sql .= "CREATE DATABASE IF NOT EXISTS `{$dbName}`;\n";
            $sql .= "USE `{$dbName}`;\n\n";
            $sql .= "-- (Dev mode: no real tables to export)\n";
            file_put_contents($dumpPath, $sql);
        } else {
            $this->sudo->run(['mysqldump', $dbName, '--result-file=' . $dumpPath]);
        }

        AuditLog::record('database.export', $dbName, ['file' => $filename]);

        return $dumpPath;
    }

    /**
     * Import a .sql file into the database.
     */
    public function importDump(DatabaseInstance $instance, string $sqlFilePath): void
    {
        $dbName = $instance->db_name;

        if (!preg_match('/^[a-zA-Z0-9_]{3,64}$/', $dbName)) {
            throw new \RuntimeException('Nombre de base de datos inválido para importación.');
        }

        if (!file_exists($sqlFilePath)) {
            throw new \RuntimeException('El archivo SQL no existe.');
        }

        AuditLog::record('database.import', $dbName, ['file' => basename($sqlFilePath)]);

        if (!app()->isProduction()) {
            Log::info("DEV: Would import {$sqlFilePath} into {$dbName}");
            return;
        }

        $sqlContent = file_get_contents($sqlFilePath);
        $this->sudo->run(['mysql', $dbName, '-e', $sqlContent]);
    }
}

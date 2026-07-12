<?php

namespace App\Services;

use App\Models\FtpAccount;
use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class FtpService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Create FTP account.
     */
    public function create(User $user, array $data): FtpAccount
    {
        $domain = Domain::where('id', $data['domain_id'])->where('user_id', $user->id)->firstOrFail();
        
        $rawUsername = trim($data['username']);
        // Clean username from invalid chars
        $rawUsername = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $rawUsername);
        
        // cPanel style username: user@domain.com
        $ftpUsername = strtolower($rawUsername) . '@' . $domain->name;

        if (FtpAccount::where('username', $ftpUsername)->exists()) {
            throw new \RuntimeException("La cuenta FTP {$ftpUsername} ya existe.");
        }

        // Home directory is relative to the domain root
        $rawSubdir = trim($data['subdir'] ?? '');
        
        if (str_starts_with($rawSubdir, '/')) {
            // Absolute path
            $homeDir = rtrim($rawSubdir, '/');
            if (empty($homeDir)) $homeDir = '/';
        } else {
            // Relative to domain root
            $subdir = trim($rawSubdir, '/');
            $relativePath = $domain->name . ($subdir ? '/' . $subdir : '');
            
            if (!app()->isProduction()) {
                $homeDir = storage_path('app/public/webroot/' . $relativePath);
            } else {
                $homeDir = config('larapanel.paths.webroots', '/var/www') . '/' . $relativePath;
            }
        }

        // Create home dir if not exists
        if (!file_exists($homeDir)) {
            if (!app()->isProduction()) {
                @mkdir($homeDir, 0755, true);
            } else {
                try {
                    $this->sudo->run(['mkdir', '-p', $homeDir]);
                    $this->sudo->run(['chown', 'www-data:www-data', $homeDir]);
                } catch (\Throwable $e) {
                    Log::error("FtpService: Failed to create FTP home dir: " . $e->getMessage());
                }
            }
        }

        // Password hash using standard MD5 crypt/SHA512-crypt or Laravel default
        $passwordHash = crypt($data['password'], '$6$' . bin2hex(random_bytes(8)) . '$');

        $account = FtpAccount::create([
            'user_id' => $user->id,
            'domain_id' => $domain->id,
            'username' => $ftpUsername,
            'password_hash' => $passwordHash,
            'home_directory' => $homeDir,
            'quota_bytes' => $data['quota_bytes'] ?? 0, // 0 = Unlimited
            'is_active' => true,
            'readonly' => $data['readonly'] ?? false,
        ]);

        AuditLog::record('ftp.created', $ftpUsername, ['home_directory' => $homeDir]);

        return $account;
    }

    /**
     * Delete FTP account.
     */
    public function delete(FtpAccount $account): void
    {
        AuditLog::record('ftp.deleted', $account->username);
        $account->forceDelete();
    }

    /**
     * Change FTP password.
     */
    public function changePassword(FtpAccount $account, string $newPassword): void
    {
        $hash = crypt($newPassword, '$6$' . bin2hex(random_bytes(8)) . '$');
        $account->update([
            'password_hash' => $hash,
        ]);

        AuditLog::record('ftp.password.changed', $account->username);
    }

    /**
     * Change FTP home directory.
     */
    public function changeHomeDirectory(FtpAccount $account, string $newSubdir): void
    {
        $rawSubdir = trim($newSubdir);
        $domain = $account->domain;
        
        if (str_starts_with($rawSubdir, '/')) {
            $homeDir = rtrim($rawSubdir, '/');
            if (empty($homeDir)) $homeDir = '/';
        } else {
            $subdir = trim($rawSubdir, '/');
            $relativePath = $domain->name . ($subdir ? '/' . $subdir : '');
            
            if (!app()->isProduction()) {
                $homeDir = storage_path('app/public/webroot/' . $relativePath);
            } else {
                $homeDir = config('larapanel.paths.webroots', '/var/www') . '/' . $relativePath;
            }
        }

        // Create home dir if not exists
        if (!file_exists($homeDir)) {
            if (!app()->isProduction()) {
                @mkdir($homeDir, 0755, true);
            } else {
                try {
                    $this->sudo->run(['mkdir', '-p', $homeDir]);
                    $this->sudo->run(['chown', 'www-data:www-data', $homeDir]);
                } catch (\Throwable $e) {
                    Log::error("FtpService: Failed to create FTP home dir: " . $e->getMessage());
                }
            }
        }

        $account->update([
            'home_directory' => $homeDir,
        ]);

        AuditLog::record('ftp.homedir.changed', $account->username, ['home_directory' => $homeDir]);
    }

    /**
     * Toggle read-only flag.
     */
    public function toggleReadonly(FtpAccount $account): void
    {
        $account->update([
            'readonly' => !$account->readonly,
        ]);

        AuditLog::record('ftp.readonly.toggled', $account->username, ['readonly' => $account->readonly]);
    }
}

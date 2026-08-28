<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use App\Shell\ShellExecutor;

class EmailService
{
    public function __construct(
        protected ShellExecutor $shell,
    ) {}

    /**
     * Create virtual email mailbox.
     */
    public function create(User $user, array $data): EmailAccount
    {
        $domain = Domain::where('id', $data['domain_id'])->where('user_id', $user->id)->firstOrFail();
        $username = strtolower(trim($data['username']));
        $email = $username . '@' . $domain->name;

        // Validation of email address format
        if (!preg_match('/^[a-z0-9_\-\.]+$/', $username)) {
            throw new \InvalidArgumentException('El nombre de usuario del correo debe ser alfanumérico minúscula, puntos o guiones.');
        }

        if (EmailAccount::where('email', $email)->exists()) {
            throw new \RuntimeException("El correo electrónico {$email} ya existe.");
        }

        // Si existe una cuenta previamente eliminada (soft delete) con el mismo
        // email, la columna UNIQUE de MySQL seguiría bloqueando la creación.
        // La eliminamos permanentemente (el maildir en disco se conserva) y
        // creamos la cuenta nueva con la contraseña indicada.
        $trashed = EmailAccount::withTrashed()->where('email', $email)->first();
        if ($trashed) {
            $trashed->forceDelete();
        }

        // Dovecot-compatible password hashing or Laravel native bcrypt
        $passwordHash = Hash::make($data['password']);

        $account = EmailAccount::create([
            'user_id' => $user->id,
            'domain_id' => $domain->id,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'quota_bytes' => $data['quota_bytes'] ?? 524288000, // 500MB
            'is_active' => true,
            'can_send' => true,
            'can_receive' => true,
            'forwarders' => $data['forwarders'] ?? [],
        ]);

        AuditLog::record('email.created', $email, ['mailbox_id' => $account->id]);

        return $account;
    }

    /**
     * Delete email mailbox.
     */
    public function delete(EmailAccount $account): void
    {
        AuditLog::record('email.deleted', $account->email);
        $account->delete();
    }

    /**
     * Change mailbox password.
     */
    public function changePassword(EmailAccount $account, string $newPassword): void
    {
        $account->update([
            'password_hash' => Hash::make($newPassword),
        ]);

        AuditLog::record('email.password.changed', $account->email);
    }

    /**
     * Suspend/unsuspend mailbox.
     */
    public function toggleStatus(EmailAccount $account): void
    {
        $account->update([
            'is_active' => !$account->is_active,
        ]);

        AuditLog::record('email.status.toggled', $account->email, ['status' => $account->is_active]);
    }

    /**
     * Update mailbox quota (in bytes).
     */
    public function updateQuota(EmailAccount $account, int $quotaBytes): void
    {
        $account->update([
            'quota_bytes' => max(0, $quotaBytes),
        ]);

        AuditLog::record('email.quota.updated', $account->email, ['quota_bytes' => $quotaBytes]);
    }

    /**
     * Update forwarders list.
     */
    public function updateForwarders(EmailAccount $account, array $forwarders): void
    {
        // Validate emails list
        $validated = [];
        foreach ($forwarders as $email) {
            $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            if ($email) {
                $validated[] = $email;
            }
        }

        $account->update([
            'forwarders' => $validated,
        ]);

        AuditLog::record('email.forwarders.updated', $account->email, ['forwarders' => $validated]);
    }

    /**
     * Import emails from a ZIP archive (cPanel format).
     */
    public function importFromZip(string $zipPath, User $user, Domain $domain, string $defaultPassword): int
    {
        $zip = new \ZipArchive;
        $res = $zip->open($zipPath);
        if ($res !== true) {
            throw new \RuntimeException("No se pudo abrir el archivo ZIP (Código de error de PHP: {$res}).");
        }

        $extractPath = sys_get_temp_dir() . '/larapanel_import_' . uniqid();
        mkdir($extractPath);
        $zip->extractTo($extractPath);
        $zip->close();
        
        $maildirPaths = $this->findMaildirs($extractPath);
        $importedCount = 0;

        $vmailBase = config('larapanel.paths.vmail', '/var/vmail');
        $domainMailPath = rtrim($vmailBase, '/') . '/' . $domain->name;

        $sudo = app(\App\Shell\SudoExecutor::class);
        $sudo->run(['mkdir', '-p', $domainMailPath]);
        $sudo->run(['chown', 'vmail:vmail', $domainMailPath], checkExit: false);

        foreach ($maildirPaths as $path) {
            $username = basename($path);
            
            // Exclude hidden folders or invalid usernames
            if (str_starts_with($username, '.') || !preg_match('/^[a-z0-9_\-\.]+$/', $username)) {
                continue; 
            }

            $email = $username . '@' . $domain->name;

            // Check if exists
            $account = EmailAccount::where('email', $email)->first();
            if (!$account) {
                $account = EmailAccount::create([
                    'user_id' => $user->id,
                    'domain_id' => $domain->id,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => Hash::make($defaultPassword),
                    'quota_bytes' => 524288000, // 500MB
                    'is_active' => true,
                    'can_send' => true,
                    'can_receive' => true,
                ]);
                AuditLog::record('email.imported', $email, ['mailbox_id' => $account->id]);
            }

            $destPath = $domainMailPath . '/' . $username;

            $sudo->run(['mkdir', '-p', $destPath]);
            // Copiar el contenido del maildir al destino
            $sudo->run(['cp', '-r', $path . '/.', $destPath . '/']);
            
            // cPanel exports sometimes have old permissions, we force vmail
            $sudo->run(['chown', '-R', 'vmail:vmail', $destPath], checkExit: false);
            // Si el sistema usa www-data para los correos
            $sudo->run(['chown', '-R', 'www-data:www-data', $destPath], checkExit: false);
            
            $importedCount++;
        }

        // Limpieza
        $sudo->run(['rm', '-rf', $extractPath]);

        return $importedCount;
    }

    private function findMaildirs(string $dir): array
    {
        $maildirs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $path = $file->getPathname();
                if (is_dir($path . '/cur') && is_dir($path . '/new')) {
                    $maildirs[] = $path;
                }
            }
        }

        // Filter out sub-maildirs (e.g. user/.Trash) so we only get the root maildir for each user
        $filtered = [];
        foreach ($maildirs as $m1) {
            $isSub = false;
            foreach ($maildirs as $m2) {
                if ($m1 !== $m2 && str_starts_with($m1, $m2 . '/')) {
                    $isSub = true;
                    break;
                }
            }
            if (!$isSub) {
                $filtered[] = rtrim($m1, '/');
            }
        }

        return $filtered;
    }

    /**
     * Refresh mailbox usage for all accounts of a user.
     */
    public function refreshUsage(int $userId): void
    {
        if (!app()->isProduction()) {
            return; // En local/dev ignoramos el cálculo pesado
        }

        $accounts = EmailAccount::with('domain')->where('user_id', $userId)->get();
        $vmailBase = config('larapanel.paths.vmail', '/var/vmail');
        $sudo = app(\App\Shell\SudoExecutor::class);

        foreach ($accounts as $account) {
            $maildir = rtrim($vmailBase, '/') . '/' . $account->domain->name . '/' . $account->username;
            $maildirsizePath = $maildir . '/maildirsize';

            $bytes = 0;

            // Intentar leer maildirsize (mucho más rápido que du)
            // Formato Dovecot: línea 1 = cabecera (cuota + flags),
            // líneas 2+ = <tamaño_carpeta> <num_mensajes> por cada subcarpeta Maildir++
            // El total real es la SUMA de todas las líneas después de la cabecera.
            if (file_exists($maildirsizePath)) {
                $content = @file_get_contents($maildirsizePath);
                if ($content !== false) {
                    $lines = array_filter(array_map('trim', explode("\n", $content)));
                    $dataLines = array_slice($lines, 1); // saltar cabecera
                    foreach ($dataLines as $line) {
                        $parts = explode(' ', $line);
                        if (count($parts) > 0 && is_numeric($parts[0])) {
                            $bytes += (int) $parts[0];
                        }
                    }
                }
            }

            // Fallback a du -sb si no hay maildirsize o dio 0
            if ($bytes <= 0 && is_dir($maildir)) {
                $duResult = $sudo->run(['du', '-sb', $maildir], checkExit: false);
                if ($duResult->successful() && $duResult->stdout !== '') {
                    $bytes = (int) explode("\t", trim($duResult->stdout))[0];
                }
            }

            // Actualizar si es diferente
            if ($bytes !== $account->used_bytes) {
                $account->update(['used_bytes' => max(0, $bytes)]);
            }
        }
    }
}

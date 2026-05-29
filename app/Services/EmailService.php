<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\Domain;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;

class EmailService
{
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
}

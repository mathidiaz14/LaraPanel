<?php

namespace App\Livewire\FTP;

use App\Models\FtpAccount;
use App\Models\Domain;
use App\Services\FtpService;
use Livewire\Component;
use Illuminate\Support\Str;

class FtpIndex extends Component
{
    // Form fields
    public string $usernameSuffix = '';
    public ?int $domainId = null;
    public string $subdir = '';
    public string $password = '';
    public int $quotaMb = 0; // 0 = unlimited
    public bool $readonly = false;
    public int $maxConnections = 5;
    public int $bandwidthLimitMb = 0; // 0 = unlimited
    public string $allowedIps = '';  // comma-separated
    public string $notes = '';

    // IP restriction modal
    public ?int $editIpId = null;
    public string $editAllowedIps = '';
    public int $editBandwidthMb  = 0;

    // Modals
    public ?int $changingPasswordId = null;
    public string $newPassword = '';

    // Success/error alerts
    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'usernameSuffix'   => 'required|string|min:2|max:20|regex:/^[a-zA-Z0-9_\-\.]+$/',
        'domainId'         => 'required|integer|exists:domains,id',
        'subdir'           => 'nullable|string|max:100',
        'password'         => 'required|string|min:8|max:64',
        'quotaMb'          => 'required|integer|min:0|max:102400',
        'readonly'         => 'boolean',
        'maxConnections'   => 'required|integer|min:1|max:50',
        'bandwidthLimitMb' => 'required|integer|min:0',
        'notes'            => 'nullable|string|max:255',
    ];

    public function generateRandomPassword(): void
    {
        $this->password = Str::random(16);
    }

    public function generateRandomNewPassword(): void
    {
        $this->newPassword = Str::random(16);
    }

    public function createFtp(FtpService $ftpService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $ftpService->create(auth()->user(), [
                'domain_id'             => $this->domainId,
                'username'              => $this->usernameSuffix,
                'subdir'                => $this->subdir,
                'password'              => $this->password,
                'quota_bytes'           => $this->quotaMb * 1024 * 1024,
                'readonly'              => $this->readonly,
                'max_connections'       => $this->maxConnections,
                'bandwidth_limit_bytes' => $this->bandwidthLimitMb * 1024 * 1024,
                'allowed_ips'           => array_filter(array_map('trim', explode(',', $this->allowedIps))),
                'notes'                 => $this->notes,
            ]);

            $this->successMessage = "Cuenta FTP creada con éxito.";

            // Reset
            $this->usernameSuffix    = '';
            $this->password          = '';
            $this->subdir            = '';
            $this->quotaMb           = 0;
            $this->readonly          = false;
            $this->maxConnections    = 5;
            $this->bandwidthLimitMb  = 0;
            $this->allowedIps        = '';
            $this->notes             = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteFtp(int $id, FtpService $ftpService): void
    {
        $account = FtpAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $ftpService->delete($account);
            $this->successMessage = "Cuenta FTP eliminada con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleReadonly(int $id, FtpService $ftpService): void
    {
        $account = FtpAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $ftpService->toggleReadonly($account);
            $this->successMessage = "Acceso de lectura/escritura de la cuenta actualizado.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openIpEdit(int $id): void
    {
        $account = FtpAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->editIpId       = $id;
        $this->editAllowedIps = implode(', ', $account->allowed_ips ?? []);
        $this->editBandwidthMb = (int)round(($account->bandwidth_limit_bytes ?? 0) / 1048576);
    }

    public function saveIpEdit(): void
    {
        $account = FtpAccount::where('id', $this->editIpId)->where('user_id', auth()->id())->firstOrFail();

        $ips = array_filter(array_map('trim', explode(',', $this->editAllowedIps)));
        $account->update([
            'allowed_ips'           => $ips ?: null,
            'bandwidth_limit_bytes' => $this->editBandwidthMb * 1048576,
        ]);

        $this->successMessage = "Restricciones de acceso actualizadas con éxito.";
        $this->editIpId = null;
    }

    public function confirmChangePassword(int $id): void
    {
        $this->changingPasswordId = $id;
        $this->newPassword = '';
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function changePassword(FtpService $ftpService): void
    {
        $this->validate([
            'newPassword' => 'required|string|min:8|max:64',
        ]);

        $account = FtpAccount::where('id', $this->changingPasswordId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $ftpService->changePassword($account, $this->newPassword);
            $this->successMessage = "Contraseña de la cuenta FTP actualizada.";
            $this->changingPasswordId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $ftps = FtpAccount::with('domain')
            ->where('user_id', auth()->id())
            ->orderBy('username')
            ->get();

        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.ftp.ftp-index', [
            'ftps' => $ftps,
            'domains' => $domains,
        ])->layout('layouts.app', [
            'title' => 'Cuentas FTP',
            'breadcrumb' => '<span>Hosting</span> / <strong>Cuentas FTP</strong>',
        ]);
    }
}

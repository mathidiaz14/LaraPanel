<?php

namespace App\Livewire\Email;

use App\Models\EmailAccount;
use App\Models\Domain;
use App\Services\EmailService;
use Livewire\Component;
use Illuminate\Support\Str;

class EmailIndex extends Component
{
    // Form fields
    public string $username = '';
    public ?int $domainId = null;
    public string $password = '';
    public int $quotaMb = 500;

    // Modals
    public ?int $changingPasswordId = null;
    public string $newPassword = '';

    public ?int $editingForwardersId = null;
    public string $forwarderInput = ''; // comma-separated emails

    // Success/error alerts
    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'username' => 'required|string|min:2|max:32|regex:/^[a-z0-9_\-\.]+$/',
        'domainId' => 'required|integer|exists:domains,id',
        'password' => 'required|string|min:8|max:64',
        'quotaMb'  => 'required|integer|min:10|max:10240',
    ];

    public function generateRandomPassword(): void
    {
        $this->password = Str::random(16);
    }

    public function generateRandomNewPassword(): void
    {
        $this->newPassword = Str::random(16);
    }

    public function createEmail(EmailService $emailService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $emailService->create(auth()->user(), [
                'domain_id' => $this->domainId,
                'username' => $this->username,
                'password' => $this->password,
                'quota_bytes' => $this->quotaMb * 1024 * 1024,
            ]);

            $this->successMessage = "Cuenta de correo creada con éxito.";
            $this->username = '';
            $this->password = '';
            $this->quotaMb = 500;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteEmail(int $id, EmailService $emailService): void
    {
        $account = EmailAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $emailService->delete($account);
            $this->successMessage = "Cuenta de correo eliminada con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleStatus(int $id, EmailService $emailService): void
    {
        $account = EmailAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $emailService->toggleStatus($account);
            $this->successMessage = "Estado de la cuenta actualizado.";
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

    public function changePassword(EmailService $emailService): void
    {
        $this->validate([
            'newPassword' => 'required|string|min:8|max:64',
        ]);

        $account = EmailAccount::where('id', $this->changingPasswordId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $emailService->changePassword($account, $this->newPassword);
            $this->successMessage = "Contraseña de la cuenta de correo actualizada.";
            $this->changingPasswordId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function editForwarders(int $id): void
    {
        $this->editingForwardersId = $id;
        $account = EmailAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->forwarderInput = implode(', ', $account->forwarders ?? []);
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function saveForwarders(EmailService $emailService): void
    {
        $account = EmailAccount::where('id', $this->editingForwardersId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $forwardersList = array_filter(array_map('trim', explode(',', $this->forwarderInput)));
            $emailService->updateForwarders($account, $forwardersList);
            $this->successMessage = "Redirecciones guardadas con éxito.";
            $this->editingForwardersId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $emails = EmailAccount::with('domain')
            ->where('user_id', auth()->id())
            ->orderBy('email')
            ->get();

        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.email.email-index', [
            'emails' => $emails,
            'domains' => $domains,
        ])->layout('layouts.app', [
            'title' => 'Cuentas de Correo',
            'breadcrumb' => '<span>Hosting</span> / <strong>Cuentas de Correo</strong>',
        ]);
    }
}

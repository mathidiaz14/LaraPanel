<?php

namespace App\Livewire\Email;

use App\Models\EmailAccount;
use App\Models\Domain;
use App\Services\EmailService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class EmailIndex extends Component
{
    use WithFileUploads;

    public function mount(EmailService $emailService): void
    {
        // Actualizar el cálculo de cuota de correo al cargar la página
        $emailService->refreshUsage(auth()->id());
    }

    // Form fields
    public string $username = '';
    public ?int $domainId = null;
    public string $password = '';
    public int $quotaMb = 500;

    // Search
    public string $search = '';

    // Modals
    public ?int $changingPasswordId = null;
    public string $newPassword = '';

    public ?int $editingForwardersId = null;
    public string $forwarderInput = ''; // comma-separated emails

    public $zipFile;
    public string $defaultImportPassword = '';
    public ?int $importDomainId = null;
    public bool $showImportModal = false;

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
            if (!auth()->user()->canAddEmailAccount()) {
                $this->errorMessage = 'Has alcanzado el límite de cuentas de correo de tu plan.';
                return;
            }

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

    public function openImportModal(): void
    {
        $this->showImportModal = true;
        $this->zipFile = null;
        $this->defaultImportPassword = Str::random(12);
        $this->importDomainId = null;
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->zipFile = null;
    }

    public function importFromZip(EmailService $emailService): void
    {
        $this->validate([
            'zipFile' => 'required|file|mimes:zip|max:512000', // max 500MB
            'defaultImportPassword' => 'required|string|min:8|max:64',
            'importDomainId' => 'required|integer|exists:domains,id',
        ]);

        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            if (!auth()->user()->canAddEmailAccount()) {
                $this->errorMessage = 'Has alcanzado el límite de cuentas de correo de tu plan.';
                return;
            }

            $domain = Domain::where('id', $this->importDomainId)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Store the zip temporarily
            $path = $this->zipFile->store('email_imports');
            $fullPath = \Illuminate\Support\Facades\Storage::path($path);

            $importedCount = $emailService->importFromZip($fullPath, auth()->user(), $domain, $this->defaultImportPassword);

            $this->successMessage = "¡Importación exitosa! Se importaron {$importedCount} cuentas de correo y sus mensajes.";
            $this->showImportModal = false;
            $this->zipFile = null;
            
            // Clean up
            @unlink($fullPath);
        } catch (\Throwable $e) {
            $this->errorMessage = "Error en la importación: " . $e->getMessage();
        }
    }

    /**
     * Generate a temporary signed auto-login URL for Roundcube webmail.
     */
    public function openWebmail(int $id): void
    {
        $account = EmailAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Store the email in cache for 90 seconds for the intermediary to pick up
        $token = Str::random(40);
        Cache::put('webmail_autologin_' . $token, $account->email, 90);

        // Generate a signed URL that expires in 90 seconds
        $url = URL::temporarySignedRoute('webmail.autologin', now()->addSeconds(90), ['token' => $token]);

        // Redirect browser to auto-login intermediary
        $this->dispatch('open-url', url: $url);
    }

    public function render()
    {
        $emails = EmailAccount::with('domain')
            ->where('user_id', auth()->id())
            ->when($this->search, fn($q) => $q->where('email', 'like', "%{$this->search}%"))
            ->orderBy('email')
            ->get()
            ->groupBy('domain_id');

        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.email.email-index', [
            'emailsByDomain' => $emails,
            'domains'        => $domains,
        ])->layout('layouts.app', [
            'title'      => 'Cuentas de Correo',
            'breadcrumb' => '<span>Hosting</span> / <strong>Cuentas de Correo</strong>',
        ]);
    }
}

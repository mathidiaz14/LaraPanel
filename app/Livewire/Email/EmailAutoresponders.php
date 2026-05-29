<?php

namespace App\Livewire\Email;

use App\Models\EmailAccount;
use App\Models\EmailAutoresponder;
use App\Models\Domain;
use App\Models\AuditLog;
use Livewire\Component;

class EmailAutoresponders extends Component
{
    // Selection
    public ?int $selectedAccountId = null;

    // Form
    public string $subject              = 'Estoy fuera de la oficina';
    public string $body                 = '';
    public string $replyFrom            = '';
    public ?string $startsAt            = null;
    public ?string $endsAt             = null;
    public int    $repeatIntervalDays  = 1;
    public bool   $isActive            = true;

    // Edit mode
    public ?int $editingId = null;

    public string $successMessage = '';
    public string $errorMessage   = '';

    protected array $rules = [
        'selectedAccountId' => 'required|integer|exists:email_accounts,id',
        'subject'           => 'required|string|max:255',
        'body'              => 'required|string|max:10000',
        'replyFrom'         => 'nullable|email|max:255',
        'startsAt'          => 'nullable|date',
        'endsAt'            => 'nullable|date|after_or_equal:startsAt',
        'repeatIntervalDays'=> 'required|integer|min:1|max:30',
    ];

    public function selectAccount(int $id): void
    {
        $account = EmailAccount::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->selectedAccountId = $id;
        $this->editingId         = null;
        $this->successMessage    = '';
        $this->errorMessage      = '';

        // Pre-fill reply_from with account email
        $this->replyFrom = $account->email;
    }

    public function saveAutoresponder(): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage   = '';

        $account = EmailAccount::where('id', $this->selectedAccountId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            if ($this->editingId) {
                $ar = EmailAutoresponder::where('id', $this->editingId)
                    ->where('email_account_id', $account->id)
                    ->firstOrFail();

                $ar->update([
                    'subject'             => $this->subject,
                    'body'                => $this->body,
                    'reply_from'          => $this->replyFrom ?: null,
                    'starts_at'           => $this->startsAt ?: null,
                    'ends_at'             => $this->endsAt   ?: null,
                    'repeat_interval_days'=> $this->repeatIntervalDays,
                    'is_active'           => $this->isActive,
                ]);

                $this->successMessage = "Autoresponder actualizado con éxito.";
                AuditLog::record('email.autoresponder.updated', $account->email);
            } else {
                EmailAutoresponder::create([
                    'email_account_id'    => $account->id,
                    'subject'             => $this->subject,
                    'body'                => $this->body,
                    'reply_from'          => $this->replyFrom ?: null,
                    'starts_at'           => $this->startsAt ?: null,
                    'ends_at'             => $this->endsAt   ?: null,
                    'repeat_interval_days'=> $this->repeatIntervalDays,
                    'is_active'           => $this->isActive,
                ]);

                $this->successMessage = "Autoresponder creado con éxito para {$account->email}.";
                AuditLog::record('email.autoresponder.created', $account->email);
            }

            $this->editingId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function editAutoresponder(int $id): void
    {
        $ar = EmailAutoresponder::where('id', $id)
            ->where('email_account_id', $this->selectedAccountId)
            ->firstOrFail();

        $this->editingId           = $id;
        $this->subject             = $ar->subject;
        $this->body                = $ar->body;
        $this->replyFrom           = $ar->reply_from ?? '';
        $this->startsAt            = $ar->starts_at?->format('Y-m-d');
        $this->endsAt              = $ar->ends_at?->format('Y-m-d');
        $this->repeatIntervalDays  = $ar->repeat_interval_days;
        $this->isActive            = $ar->is_active;
    }

    public function toggleAutoresponder(int $id): void
    {
        $ar = EmailAutoresponder::find($id);
        if ($ar) {
            $ar->update(['is_active' => !$ar->is_active]);
            $this->successMessage = $ar->is_active ? "Autoresponder activado." : "Autoresponder desactivado.";
        }
    }

    public function deleteAutoresponder(int $id): void
    {
        $ar = EmailAutoresponder::find($id);
        if ($ar) {
            AuditLog::record('email.autoresponder.deleted', '');
            $ar->delete();
            $this->successMessage = "Autoresponder eliminado con éxito.";
            if ($this->editingId === $id) $this->editingId = null;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['subject', 'body', 'replyFrom', 'startsAt', 'endsAt']);
        $this->subject = 'Estoy fuera de la oficina';
        $this->repeatIntervalDays = 1;
        $this->isActive = true;
    }

    public function render()
    {
        $accounts = EmailAccount::with('domain')
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('email')
            ->get();

        $autoresponders = $this->selectedAccountId
            ? EmailAutoresponder::where('email_account_id', $this->selectedAccountId)->get()
            : collect();

        return view('livewire.email.email-autoresponders', [
            'accounts'       => $accounts,
            'autoresponders' => $autoresponders,
        ])->layout('layouts.app', [
            'title'      => 'Autoresponders de Email',
            'breadcrumb' => '<span><a href="' . route('email.index') . '">Email</a></span> / <strong>Autoresponders</strong>',
        ]);
    }
}

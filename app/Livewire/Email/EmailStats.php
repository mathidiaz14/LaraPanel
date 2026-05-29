<?php

namespace App\Livewire\Email;

use App\Models\EmailAccount;
use App\Models\Domain;
use Livewire\Component;

class EmailStats extends Component
{
    public ?int $selectedDomainId = null;

    public string $successMessage = '';
    public string $errorMessage   = '';

    public function selectDomain(int $id): void
    {
        $this->selectedDomainId = $id;
    }

    /**
     * Refresh mailbox usage from disk (production only).
     */
    public function refreshUsage(): void
    {
        if (!$this->selectedDomainId) return;

        $accounts = EmailAccount::where('user_id', auth()->id())
            ->where('domain_id', $this->selectedDomainId)
            ->get();

        if (!app()->isProduction()) {
            // Simulate random usage in dev
            foreach ($accounts as $account) {
                $account->update(['used_bytes' => rand(1024 * 1024, 400 * 1024 * 1024)]);
            }
            $this->successMessage = "Estadísticas actualizadas (modo simulación).";
            return;
        }

        $mailboxRoot = config('larapanel.mail.mailboxes_root', '/var/mail/vhosts');

        foreach ($accounts as $account) {
            $mailboxPath = $mailboxRoot . '/' . $account->domain->name . '/' . $account->username;
            if (is_dir($mailboxPath)) {
                $output = [];
                exec('du -sb ' . escapeshellarg($mailboxPath) . ' 2>/dev/null', $output);
                if (!empty($output[0])) {
                    $bytes = (int)explode("\t", $output[0])[0];
                    $account->update(['used_bytes' => $bytes]);
                }
            }
        }

        $this->successMessage = "Uso de disco actualizado desde el servidor.";
    }

    public function render()
    {
        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $accounts = $this->selectedDomainId
            ? EmailAccount::with('domain')
                ->where('user_id', auth()->id())
                ->where('domain_id', $this->selectedDomainId)
                ->orderBy('email')
                ->get()
            : collect();

        // Summary stats for selected domain
        $totalQuota  = $accounts->sum('quota_bytes');
        $totalUsed   = $accounts->sum('used_bytes');
        $usedPercent = $totalQuota > 0 ? round(($totalUsed / $totalQuota) * 100, 1) : 0;

        // Global stats across all domains
        $globalAccounts = EmailAccount::where('user_id', auth()->id())->count();
        $activeAccounts = EmailAccount::where('user_id', auth()->id())->where('is_active', true)->count();

        return view('livewire.email.email-stats', [
            'domains'        => $domains,
            'accounts'       => $accounts,
            'totalQuota'     => $totalQuota,
            'totalUsed'      => $totalUsed,
            'usedPercent'    => $usedPercent,
            'globalAccounts' => $globalAccounts,
            'activeAccounts' => $activeAccounts,
        ])->layout('layouts.app', [
            'title'      => 'Estadísticas de Email',
            'breadcrumb' => '<span><a href="' . route('email.index') . '">Email</a></span> / <strong>Estadísticas</strong>',
        ]);
    }
}

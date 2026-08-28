<?php

namespace App\Livewire\Email;

use App\Models\EmailAccount;
use App\Models\Domain;
use App\Services\QuotaService;
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
            // En desarrollo calculamos el tamaño real del buzón desde disco
            // (sin datos aleatorios) para mantener la métrica determinista.
            $vmail = config('larapanel.paths.vmail', '/var/vmail');
            $quota = app(QuotaService::class);

            foreach ($accounts as $account) {
                $mailboxPath = $vmail . '/' . $account->domain->name . '/' . $account->username;
                $bytes = $quota->dirSize($mailboxPath);
                $account->update(['used_bytes' => max(0, $bytes)]);
            }
            $this->successMessage = "Estadísticas actualizadas.";
            return;
        }

        $mailboxRoot = config('larapanel.paths.vmail', '/var/vmail');
        $sudo = app(\App\Shell\SudoExecutor::class);

        foreach ($accounts as $account) {
            $mailboxPath = $mailboxRoot . '/' . $account->domain->name . '/' . $account->username;
            $maildirsizePath = $mailboxPath . '/maildirsize';
            $bytes = 0;

            // Intentar leer maildirsize (más rápido que du)
            if (file_exists($maildirsizePath)) {
                $content = @file_get_contents($maildirsizePath);
                if ($content !== false) {
                    $lines = array_filter(array_map('trim', explode("\n", $content)));
                    $dataLines = array_slice($lines, 1);
                    foreach ($dataLines as $line) {
                        $parts = explode(' ', $line);
                        if (count($parts) > 0 && is_numeric($parts[0])) {
                            $bytes += (int) $parts[0];
                        }
                    }
                }
            }

            // Fallback a du -sb
            if ($bytes <= 0 && is_dir($mailboxPath)) {
                $duResult = $sudo->run(['du', '-sb', $mailboxPath], checkExit: false);
                if ($duResult->successful() && $duResult->stdout !== '') {
                    $bytes = (int) explode("\t", trim($duResult->stdout))[0];
                }
            }

            $account->update(['used_bytes' => max(0, $bytes)]);
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

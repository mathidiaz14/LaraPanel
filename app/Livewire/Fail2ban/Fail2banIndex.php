<?php

namespace App\Livewire\Fail2ban;

use App\Models\Fail2banEvent;
use App\Services\Fail2banService;
use Livewire\Component;

class Fail2banIndex extends Component
{
    // State
    public bool   $isRunning      = false;
    public array  $jails          = [];
    public ?string $selectedJail  = null;
    public array  $jailStatus     = [];

    // Active tab
    public string $activeTab = 'dashboard'; // dashboard | jails | log | history

    // Manual ban form
    public string $banIp     = '';
    public string $banReason = '';
    public string $banJail   = '';

    // Unban confirmation
    public ?string $unbanIp   = null;
    public ?string $unbanJail = null;

    public string $successMessage = '';
    public string $errorMessage   = '';

    protected array $rules = [
        'banIp'    => 'required|ip',
        'banJail'  => 'required|string',
        'banReason'=> 'nullable|string|max:255',
    ];

    public function mount(Fail2banService $f2b): void
    {
        $status          = $f2b->getStatus();
        $this->isRunning = $status['running'];
        $this->jails     = $status['jails'];
        $this->banJail   = $this->jails[0] ?? 'sshd';

        if (!$this->isRunning) {
            $this->errorMessage = "El servicio fail2ban no está activo. Razón: " . ($status['raw_output'] ?? 'desconocida');
        }
    }

    public function selectJail(string $jail, Fail2banService $f2b): void
    {
        $this->selectedJail = $jail;
        $this->jailStatus   = $f2b->getJailStatus($jail);
        $this->clearMessages();
    }

    public function refreshJail(Fail2banService $f2b): void
    {
        if ($this->selectedJail) {
            $this->jailStatus = $f2b->getJailStatus($this->selectedJail);
        }
    }

    public function banIp(Fail2banService $f2b): void
    {
        $this->validate();
        $this->clearMessages();

        try {
            $f2b->banIp(auth()->user(), $this->banJail, $this->banIp, $this->banReason);
            $this->successMessage = "IP {$this->banIp} baneada en jail «{$this->banJail}».";
            $this->banIp     = '';
            $this->banReason = '';

            // Refresh jail if it's the selected one
            if ($this->selectedJail === $this->banJail) {
                $this->jailStatus = $f2b->getJailStatus($this->selectedJail);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirmUnban(string $jail, string $ip): void
    {
        $this->unbanJail = $jail;
        $this->unbanIp   = $ip;
    }

    public function unbanIp(Fail2banService $f2b): void
    {
        if (!$this->unbanIp || !$this->unbanJail) return;
        $this->clearMessages();

        try {
            $f2b->unbanIp(auth()->user(), $this->unbanJail, $this->unbanIp);
            $this->successMessage = "IP {$this->unbanIp} desbaneada de «{$this->unbanJail}».";

            if ($this->selectedJail === $this->unbanJail) {
                $this->jailStatus = $f2b->getJailStatus($this->selectedJail);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->unbanIp = $this->unbanJail = null;
        }
    }

    public function unbanGlobal(string $ip, Fail2banService $f2b): void
    {
        $this->clearMessages();
        try {
            $unbanned = $f2b->unbanIpGlobal(auth()->user(), $ip);
            $this->successMessage = "IP {$ip} desbaneada de " . count($unbanned) . " jail(s): " . implode(', ', $unbanned);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function restartFail2ban(Fail2banService $f2b): void
    {
        $this->clearMessages();
        try {
            $f2b->restart();
            $this->successMessage = "Fail2ban reiniciado con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function reloadConfig(Fail2banService $f2b): void
    {
        $this->clearMessages();
        try {
            $f2b->reload();
            $this->successMessage = "Configuración de Fail2ban recargada.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render(Fail2banService $f2b)
    {
        $status     = $f2b->getStatus();
        $globalStats = cache()->remember('f2b_global_stats', 30, fn() => $f2b->getGlobalStats());

        $logTail = $this->activeTab === 'log'
            ? $f2b->getLogTail(80)
            : '';

        $history = $this->activeTab === 'history'
            ? Fail2banEvent::where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->take(100)
                ->get()
            : collect();

        return view('livewire.fail2ban.fail2ban-index', [
            'status'      => $status,
            'globalStats' => $globalStats,
            'logTail'     => $logTail,
            'history'     => $history,
        ])->layout('layouts.app', [
            'title'      => 'Fail2ban — Protección contra Ataques',
            'breadcrumb' => '<span>Seguridad</span> / <strong>Fail2ban</strong>',
        ]);
    }
}

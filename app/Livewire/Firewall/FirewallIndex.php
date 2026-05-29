<?php

namespace App\Livewire\Firewall;

use App\Models\FirewallRule;
use App\Services\FirewallService;
use Livewire\Component;

class FirewallIndex extends Component
{
    // UFW Status
    public bool  $ufwEnabled = false;
    public string $defaultIn  = 'deny';
    public string $defaultOut = 'allow';

    // Active tab
    public string $activeTab = 'rules'; // rules | presets | raw

    // Add rule form
    public string $ruleName      = '';
    public string $ruleAction    = 'allow';
    public string $rulePort      = '';
    public string $ruleProtocol  = 'tcp';
    public string $ruleSourceIp  = '';
    public string $ruleDirection = 'in';
    public string $ruleNotes     = '';

    // Preset selection
    public array $selectedPresets = [];

    // Confirm reset
    public bool $confirmReset = false;

    public string $successMessage = '';
    public string $errorMessage   = '';

    protected array $rules = [
        'ruleName'     => 'required|string|max:100',
        'ruleAction'   => 'required|in:allow,deny,reject,limit',
        'rulePort'     => 'nullable|string|max:20',
        'ruleProtocol' => 'required|in:tcp,udp,any',
        'ruleSourceIp' => 'nullable|string|max:45',
        'ruleDirection'=> 'required|in:in,out,any',
    ];

    public function mount(FirewallService $firewallService): void
    {
        $status           = $firewallService->getStatus();
        $this->ufwEnabled = $status['enabled'];
        $this->defaultIn  = $status['default_in'];
        $this->defaultOut = $status['default_out'];
    }

    public function enableFirewall(FirewallService $fw): void
    {
        $this->clearMessages();
        try {
            $fw->enable();
            $this->ufwEnabled     = true;
            $this->successMessage = "UFW activado con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function disableFirewall(FirewallService $fw): void
    {
        $this->clearMessages();
        try {
            $fw->disable();
            $this->ufwEnabled     = false;
            $this->successMessage = "UFW desactivado. El servidor queda sin firewall activo.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function resetFirewall(FirewallService $fw): void
    {
        $this->clearMessages();
        try {
            $fw->reset();
            $this->confirmReset   = false;
            $this->successMessage = "Firewall reseteado. SSH (22/tcp) re-agregado automáticamente.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function addRule(FirewallService $fw): void
    {
        $this->validate();
        $this->clearMessages();

        try {
            $fw->addRule(auth()->user(), [
                'name'      => $this->ruleName,
                'action'    => $this->ruleAction,
                'port'      => $this->rulePort ?: null,
                'protocol'  => $this->ruleProtocol,
                'source_ip' => $this->ruleSourceIp ?: null,
                'direction' => $this->ruleDirection,
                'notes'     => $this->ruleNotes,
            ]);

            $this->successMessage = "Regla «{$this->ruleName}» añadida con éxito.";
            $this->resetForm();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function applyPresets(FirewallService $fw): void
    {
        $this->clearMessages();
        if (empty($this->selectedPresets)) {
            $this->errorMessage = "Selecciona al menos un preset.";
            return;
        }

        $added = 0;
        foreach ($this->selectedPresets as $key) {
            $preset = FirewallService::PRESETS[$key] ?? null;
            if (!$preset) continue;

            try {
                $fw->addRule(auth()->user(), $preset);
                $added++;
            } catch (\Throwable $e) {
                // Skip duplicates silently
            }
        }

        $this->successMessage = "{$added} preset(s) aplicados con éxito.";
        $this->selectedPresets = [];
    }

    public function installDefaults(FirewallService $fw): void
    {
        $this->clearMessages();
        try {
            $count = $fw->installPresets(auth()->user());
            $this->successMessage = "{$count} reglas esenciales instaladas (SSH + HTTP + HTTPS).";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleRule(int $id, FirewallService $fw): void
    {
        $rule = FirewallRule::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->clearMessages();
        try {
            $fw->toggleRule($rule);
            $this->successMessage = "Regla «{$rule->name}» " . ($rule->is_active ? "activada." : "desactivada.");
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteRule(int $id, FirewallService $fw): void
    {
        $rule = FirewallRule::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->clearMessages();
        try {
            $fw->deleteRule($rule);
            $this->successMessage = "Regla eliminada con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function togglePreset(string $key): void
    {
        if (in_array($key, $this->selectedPresets)) {
            $this->selectedPresets = array_values(array_diff($this->selectedPresets, [$key]));
        } else {
            $this->selectedPresets[] = $key;
        }
    }

    protected function resetForm(): void
    {
        $this->ruleName = $this->rulePort = $this->ruleSourceIp = $this->ruleNotes = '';
        $this->ruleAction    = 'allow';
        $this->ruleProtocol  = 'tcp';
        $this->ruleDirection = 'in';
    }

    protected function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function render(FirewallService $fw)
    {
        $rules = FirewallRule::where('user_id', auth()->id())
            ->orderBy('sort_order')
            ->orderBy('action')
            ->get();

        $status = $fw->getStatus();
        $this->ufwEnabled = $status['enabled'];

        $connStats = cache()->remember('fw_conn_stats_' . auth()->id(), 10, fn() => $fw->getConnectionStats());

        return view('livewire.firewall.firewall-index', [
            'rules'     => $rules,
            'status'    => $status,
            'connStats' => $connStats,
            'presets'   => FirewallService::PRESETS,
        ])->layout('layouts.app', [
            'title'      => 'Firewall — UFW',
            'breadcrumb' => '<span>Seguridad</span> / <strong>Firewall</strong>',
        ]);
    }
}

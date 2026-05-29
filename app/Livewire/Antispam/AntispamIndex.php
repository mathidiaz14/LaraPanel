<?php

namespace App\Livewire\Antispam;

use App\Models\SpamRule;
use App\Services\AntispamService;
use Livewire\Component;

class AntispamIndex extends Component
{
    // New rule form
    public string $ruleType  = 'whitelist_ip';
    public string $ruleValue = '';
    public string $ruleAction= 'skip';
    public string $ruleNotes = '';
    public float  $ruleScore = 0.0;

    // Test message
    public string $testMessage = '';
    public ?array $testResult  = null;

    public string $successMessage = '';
    public string $errorMessage   = '';

    public string $activeTab = 'dashboard';  // dashboard | rules | test

    protected array $rules = [
        'ruleType'  => 'required|in:whitelist_ip,whitelist_email,whitelist_domain,blacklist_ip,blacklist_email,blacklist_domain',
        'ruleValue' => 'required|string|max:255',
        'ruleAction'=> 'required|in:skip,reject,score_add',
    ];

    public function addRule(AntispamService $antispamService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage   = '';

        try {
            $antispamService->addRule(auth()->user(), [
                'type'           => $this->ruleType,
                'value'          => $this->ruleValue,
                'action'         => $this->ruleAction,
                'score_modifier' => $this->ruleScore,
                'notes'          => $this->ruleNotes,
            ]);

            $this->successMessage = "Regla «{$this->ruleValue}» agregada con éxito.";
            $this->reset(['ruleValue', 'ruleNotes', 'ruleScore']);
            $this->ruleType   = 'whitelist_ip';
            $this->ruleAction = 'skip';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteRule(int $id, AntispamService $antispamService): void
    {
        $rule = SpamRule::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $antispamService->deleteRule($rule);
            $this->successMessage = "Regla eliminada con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleRule(int $id, AntispamService $antispamService): void
    {
        $rule = SpamRule::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $antispamService->toggleRule($rule);
        $this->successMessage = "Estado de la regla actualizado.";
    }

    public function testScan(AntispamService $antispamService): void
    {
        if (empty(trim($this->testMessage))) {
            $this->errorMessage = "Ingresa un mensaje de prueba para escanear.";
            return;
        }

        $this->successMessage = '';
        $this->errorMessage   = '';

        try {
            $this->testResult = $antispamService->testMessage($this->testMessage);
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al escanear: " . $e->getMessage();
        }
    }

    public function render(AntispamService $antispamService)
    {
        $stats = cache()->remember('rspamd_stats_' . auth()->id(), 30, fn() => $antispamService->getStats());
        $history = $this->activeTab === 'dashboard'
            ? cache()->remember('rspamd_history_' . auth()->id(), 30, fn() => $antispamService->getHistory())
            : [];

        $rules = SpamRule::where('user_id', auth()->id())->orderBy('type')->orderBy('value')->get();

        return view('livewire.antispam.antispam-index', [
            'stats'   => $stats,
            'history' => $history,
            'rules'   => $rules,
        ])->layout('layouts.app', [
            'title'      => 'Antispam — Rspamd',
            'breadcrumb' => '<span>Seguridad</span> / <strong>Antispam</strong>',
        ]);
    }
}

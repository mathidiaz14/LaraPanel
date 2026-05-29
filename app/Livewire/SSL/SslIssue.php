<?php

namespace App\Livewire\SSL;

use App\Models\Domain;
use App\Services\SslService;
use Livewire\Component;

/**
 * SslIssue — Let's Encrypt certificate issuance form.
 */
class SslIssue extends Component
{
    public ?int    $domainId   = null;
    public bool    $includeWww = true;
    public array   $extraSans  = [];
    public string  $newSan     = '';
    public bool    $isLoading  = false;
    public string  $errorMsg   = '';
    public bool    $success    = false;
    public ?string $successMsg = null;

    protected array $rules = [
        'domainId'   => 'required|integer|exists:domains,id',
        'includeWww' => 'boolean',
        'newSan'     => 'nullable|string|max:253',
    ];

    public function getDomains()
    {
        return Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function addSan(): void
    {
        $san = strtolower(trim($this->newSan));
        if ($san && !in_array($san, $this->extraSans) && count($this->extraSans) < 10) {
            $this->extraSans[] = $san;
        }
        $this->newSan = '';
    }

    public function removeSan(int $index): void
    {
        array_splice($this->extraSans, $index, 1);
        $this->extraSans = array_values($this->extraSans);
    }

    public function issue(SslService $sslService): void
    {
        $this->validate(['domainId' => 'required|integer|exists:domains,id']);

        $domain = Domain::where('id', $this->domainId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $this->isLoading = true;
        $this->errorMsg  = '';

        try {
            $cert = $sslService->issueLetsEncrypt(
                domain:    $domain,
                sanDomains: $this->extraSans,
                includeWww: $this->includeWww,
            );

            $this->success    = true;
            $this->successMsg = "¡Certificado SSL emitido correctamente para {$domain->name}! Expira el {$cert->expires_at?->format('d/m/Y')}.";
            $this->isLoading  = false;

        } catch (\Throwable $e) {
            $this->errorMsg  = $e->getMessage();
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.ssl.ssl-issue', [
            'domains' => $this->getDomains(),
        ])->layout('layouts.app', [
            'title'      => 'Emitir Certificado SSL',
            'breadcrumb' => '<span>SSL / TLS</span> / <strong>Let\'s Encrypt</strong>',
        ]);
    }
}

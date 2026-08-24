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
    public bool    $isWildcard = true;
    public array   $extraSans  = [];
    public string  $newSan     = '';
    public string  $errorMsg   = '';
    public bool    $success    = false;
    public ?string $successMsg = null;

    protected array $rules = [
        'domainId'   => 'required|integer|exists:domains,id',
        'includeWww' => 'boolean',
        'isWildcard' => 'boolean',
        'newSan'     => 'nullable|string|max:253',
    ];

    public function mount(?int $domain = null): void
    {
        if ($domain) {
            $d = Domain::where('id', $domain)->where('user_id', auth()->id())->first();
            if ($d) {
                $this->domainId   = $d->id;
                $this->isWildcard = $d->type === 'main';
            }
        }
    }

    public function getDomains()
    {
        return Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function updatedDomainId($value): void
    {
        if (!$value) return;

        $d = Domain::find($value);
        $this->isWildcard = $d && $d->type === 'main';
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

        $this->errorMsg  = '';

        try {
            $cert = $sslService->issueLetsEncrypt(
                domain:     $domain,
                sanDomains: $this->extraSans,
                includeWww: $this->includeWww,
                isWildcard: $this->isWildcard,
            );

            $this->success = true;

            if ($this->isWildcard) {
                $covered = Domain::where('user_id', auth()->id())
                    ->where('id', '!=', $domain->id)
                    ->where('is_active', true)
                    ->get()
                    ->filter(fn (Domain $d) => str_ends_with($d->name, '.' . $domain->name))
                    ->count();

                $this->successMsg = "¡Certificado Wildcard emitido para {$domain->name}! Cubre el dominio y sus subdominios"
                    . ($covered > 0 ? " ({$covered} subdominio(s) protegidos con HTTPS)" : '')
                    . ". Expira el {$cert->expires_at?->format('d/m/Y')}.";
            } else {
                $this->successMsg = "¡Certificado SSL emitido correctamente para {$domain->name}! Expira el {$cert->expires_at?->format('d/m/Y')}.";
            }

        } catch (\Throwable $e) {
            $this->errorMsg  = $e->getMessage();
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

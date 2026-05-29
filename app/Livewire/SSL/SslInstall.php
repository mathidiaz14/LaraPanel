<?php

namespace App\Livewire\SSL;

use App\Models\Domain;
use App\Services\SslService;
use Livewire\Component;

/**
 * SslInstall — Custom certificate installation form.
 * Allows pasting PEM-encoded certificate + private key + optional CA chain.
 */
class SslInstall extends Component
{
    public ?int    $domainId    = null;
    public string  $certificate = '';
    public string  $privateKey  = '';
    public string  $caChain     = '';
    public bool    $isLoading   = false;
    public string  $errorMsg    = '';
    public bool    $success     = false;
    public ?string $successMsg  = null;
    public array   $certInfo    = [];

    protected array $rules = [
        'domainId'    => 'required|integer|exists:domains,id',
        'certificate' => 'required|string|min:100',
        'privateKey'  => 'required|string|min:100',
        'caChain'     => 'nullable|string',
    ];

    public function getDomains()
    {
        return Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Live preview — parse the pasted certificate to show info.
     */
    public function updatedCertificate(SslService $sslService): void
    {
        if (strlen($this->certificate) > 100 && str_contains($this->certificate, 'BEGIN CERTIFICATE')) {
            try {
                $this->certInfo = $sslService->getCertificateInfo($this->certificate);
            } catch (\Throwable) {
                $this->certInfo = [];
            }
        } else {
            $this->certInfo = [];
        }
    }

    public function install(SslService $sslService): void
    {
        $this->validate();

        $domain = Domain::where('id', $this->domainId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $this->isLoading = true;
        $this->errorMsg  = '';

        try {
            $cert = $sslService->installCustomCertificate(
                domain:      $domain,
                certificate: $this->certificate,
                privateKey:  $this->privateKey,
                chain:       $this->caChain,
            );

            $this->success    = true;
            $this->successMsg = "¡Certificado instalado para {$domain->name}! Expira el {$cert->expires_at?->format('d/m/Y')}.";
            $this->isLoading  = false;
            $this->certificate= '';
            $this->privateKey = '';
            $this->caChain    = '';

        } catch (\Throwable $e) {
            $this->errorMsg  = $e->getMessage();
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.ssl.ssl-install', [
            'domains' => $this->getDomains(),
        ])->layout('layouts.app', [
            'title'      => 'Instalar Certificado SSL',
            'breadcrumb' => '<span>SSL / TLS</span> / <strong>Instalar Certificado</strong>',
        ]);
    }
}

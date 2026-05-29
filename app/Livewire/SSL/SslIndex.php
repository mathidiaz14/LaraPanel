<?php

namespace App\Livewire\SSL;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Services\SslService;
use Livewire\Component;

class SslIndex extends Component
{
    public ?int $revokingId = null;

    public function getCertificates()
    {
        return SslCertificate::with('domain')
            ->whereHas('domain', fn($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('created_at')
            ->get();
    }

    public function getDomainsWithoutSsl()
    {
        return Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('ssl_enabled', false)
            ->get();
    }

    public function confirmRevoke(int $certId): void
    {
        $this->revokingId = $certId;
    }

    public function revokeCertificate(SslService $sslService): void
    {
        $cert = SslCertificate::findOrFail($this->revokingId);
        // Verify ownership via domain
        abort_unless($cert->domain?->user_id === auth()->id(), 403);

        $sslService->revoke($cert->domain);
        $this->revokingId = null;
        session()->flash('success', 'Certificado SSL revocado correctamente.');
    }

    public function render()
    {
        return view('livewire.ssl.ssl-index', [
            'certificates'     => $this->getCertificates(),
            'domainsWithoutSsl'=> $this->getDomainsWithoutSsl(),
        ])->layout('layouts.app', [
            'title'      => 'SSL / TLS',
            'breadcrumb' => '<span>Avanzado</span> / <strong>SSL / TLS</strong>',
        ]);
    }
}

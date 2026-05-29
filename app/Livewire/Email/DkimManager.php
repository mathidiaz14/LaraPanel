<?php

namespace App\Livewire\Email;

use App\Models\DkimKey;
use App\Models\DnsZone;
use App\Models\Domain;
use App\Models\DnsRecord;
use App\Services\DkimService;
use App\Services\DnsService;
use Livewire\Component;

class DkimManager extends Component
{
    public ?int    $selectedDomainId = null;
    public ?Domain $selectedDomain   = null;
    public string  $dmarcPolicy      = 'none'; // none | quarantine | reject
    public string  $successMessage   = '';
    public string  $errorMessage     = '';
    public bool    $isVerifying      = false;
    public array   $verifyResults    = [];

    public function selectDomain(int $id): void
    {
        $this->selectedDomainId = $id;
        $this->selectedDomain   = Domain::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->verifyResults    = [];
        $this->successMessage   = '';
        $this->errorMessage     = '';
    }

    public function generateDkim(DkimService $dkimService): void
    {
        if (!$this->selectedDomain) return;

        $this->successMessage = '';
        $this->errorMessage   = '';

        try {
            $dkimKey = $dkimService->generateKeyPair($this->selectedDomain, 'mail');

            // Deploy to DNS zone if exists
            $zone = DnsZone::where('domain_id', $this->selectedDomain->id)->first();
            if ($zone) {
                $dkimService->deployToDns($dkimKey, $zone);
                $dkimService->configureRspamdSigning($this->selectedDomain, $dkimKey);
                $this->successMessage = "Par de claves DKIM generado y desplegado en el DNS de {$this->selectedDomain->name}.";
            } else {
                $this->successMessage = "Par de claves DKIM generado para {$this->selectedDomain->name}. Créa una zona DNS para desplegarlo automáticamente.";
            }
        } catch (\Throwable $e) {
            $this->errorMessage = "Error al generar DKIM: " . $e->getMessage();
        }
    }

    public function addSpfRecord(DkimService $dkimService, DnsService $dnsService): void
    {
        if (!$this->selectedDomain) return;

        $zone = DnsZone::where('domain_id', $this->selectedDomain->id)->first();
        if (!$zone) {
            $this->errorMessage = "Primero crea una zona DNS para {$this->selectedDomain->name}.";
            return;
        }

        // Remove existing SPF records
        DnsRecord::where('dns_zone_id', $zone->id)
            ->where('type', 'TXT')
            ->where('name', '@')
            ->where('content', 'like', '%v=spf1%')
            ->get()
            ->each(fn($r) => $dnsService->deleteRecord($r));

        $spf = $dkimService->generateSpfRecord($this->selectedDomain);
        $dnsService->createRecord($zone, [
            'name' => '@', 'type' => 'TXT', 'content' => $spf, 'ttl' => 3600, 'priority' => 0,
            'comment' => 'SPF record — LaraPanel',
        ]);

        $this->successMessage = "Registro SPF agregado a la zona DNS de {$this->selectedDomain->name}.";
    }

    public function addDmarcRecord(DkimService $dkimService, DnsService $dnsService): void
    {
        if (!$this->selectedDomain) return;

        $zone = DnsZone::where('domain_id', $this->selectedDomain->id)->first();
        if (!$zone) {
            $this->errorMessage = "Primero crea una zona DNS para {$this->selectedDomain->name}.";
            return;
        }

        // Remove existing DMARC records
        DnsRecord::where('dns_zone_id', $zone->id)
            ->where('type', 'TXT')
            ->where('name', '_dmarc')
            ->get()
            ->each(fn($r) => $dnsService->deleteRecord($r));

        $dmarc = $dkimService->generateDmarcRecord($this->selectedDomain, $this->dmarcPolicy);
        $dnsService->createRecord($zone, [
            'name' => '_dmarc', 'type' => 'TXT', 'content' => $dmarc, 'ttl' => 3600, 'priority' => 0,
            'comment' => 'DMARC policy — LaraPanel',
        ]);

        $this->successMessage = "Registro DMARC (p={$this->dmarcPolicy}) agregado a la zona DNS.";
    }

    public function verifyRecords(DkimService $dkimService): void
    {
        if (!$this->selectedDomain) return;

        $this->isVerifying  = true;
        $this->verifyResults = $dkimService->verifyAllRecords($this->selectedDomain);
        $this->isVerifying  = false;
    }

    public function render()
    {
        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $dkimKeys = $this->selectedDomainId
            ? DkimKey::where('domain_id', $this->selectedDomainId)->get()
            : collect();

        $dnsZone = $this->selectedDomainId
            ? DnsZone::with('records')->where('domain_id', $this->selectedDomainId)->first()
            : null;

        return view('livewire.email.dkim-manager', [
            'domains'  => $domains,
            'dkimKeys' => $dkimKeys,
            'dnsZone'  => $dnsZone,
        ])->layout('layouts.app', [
            'title'      => 'DKIM / SPF / DMARC',
            'breadcrumb' => '<span><a href="' . route('email.index') . '">Email</a></span> / <strong>Seguridad DKIM/SPF/DMARC</strong>',
        ]);
    }
}

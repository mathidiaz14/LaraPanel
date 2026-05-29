<?php

namespace App\Livewire\DNS;

use App\Models\DnsZone;
use App\Models\Domain;
use App\Services\DnsService;
use Livewire\Component;

class DnsIndex extends Component
{
    public bool $showCreate = false;
    public ?int $domainId = null;
    public string $successMessage = '';
    public string $errorMessage = '';

    protected array $rules = [
        'domainId' => 'required|integer|exists:domains,id',
    ];

    public function createZone(DnsService $dnsService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        $domain = Domain::where('id', $this->domainId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $zone = $dnsService->createZone(auth()->user(), $domain);
            $this->successMessage = "Zona DNS para {$domain->name} creada con éxito con " . $zone->records()->count() . " registros por defecto.";
            $this->showCreate = false;
            $this->domainId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteZone(int $id, DnsService $dnsService): void
    {
        $zone = DnsZone::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $dnsService->deleteZone($zone);
            $this->successMessage = "Zona DNS eliminada con éxito.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function applyEmailTemplate(int $id, DnsService $dnsService): void
    {
        $zone = DnsZone::with('domain')->where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        try {
            $dnsService->applyEmailTemplate($zone, $zone->domain);
            $this->successMessage = "Plantilla de email aplicada: MX, SPF y DMARC agregados a {$zone->name}.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $zones = DnsZone::with(['domain', 'records'])
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        $domainsWithoutZone = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->whereNotIn('id', $zones->pluck('domain_id')->filter())
            ->orderBy('name')
            ->get();

        return view('livewire.dns.dns-index', [
            'zones'              => $zones,
            'domainsWithoutZone' => $domainsWithoutZone,
        ])->layout('layouts.app', [
            'title'      => 'DNS Manager',
            'breadcrumb' => '<span>Avanzado</span> / <strong>DNS Manager</strong>',
        ]);
    }
}

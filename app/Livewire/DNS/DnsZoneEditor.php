<?php

namespace App\Livewire\DNS;

use App\Models\DnsZone;
use App\Models\DnsRecord;
use App\Services\DnsService;
use Livewire\Component;

class DnsZoneEditor extends Component
{
    public DnsZone $zone;

    // Inline add record form
    public string $newName     = '@';
    public string $newType     = 'A';
    public string $newContent  = '';
    public int    $newTtl      = 3600;
    public int    $newPriority = 0;
    public string $newComment  = '';

    // Editing existing record
    public ?int   $editingId      = null;
    public string $editName       = '';
    public string $editType       = '';
    public string $editContent    = '';
    public int    $editTtl        = 3600;
    public int    $editPriority   = 0;

    public string $successMessage = '';
    public string $errorMessage   = '';

    // Available record types
    public array $recordTypes = ['A','AAAA','CNAME','MX','TXT','NS','SRV','CAA','PTR','ALIAS'];

    protected array $rules = [
        'newName'     => 'required|string|max:255',
        'newType'     => 'required|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA,PTR,ALIAS',
        'newContent'  => 'required|string|max:4096',
        'newTtl'      => 'required|integer|min:60|max:86400',
        'newPriority' => 'required|integer|min:0|max:65535',
    ];

    public function mount(DnsZone $zone): void
    {
        // Verify ownership
        abort_unless($zone->user_id === auth()->id(), 403);
        $this->zone = $zone->load('records');
    }

    public function addRecord(DnsService $dnsService): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage = '';

        try {
            $dnsService->createRecord($this->zone, [
                'name'     => $this->newName,
                'type'     => $this->newType,
                'content'  => $this->newContent,
                'ttl'      => $this->newTtl,
                'priority' => $this->newPriority,
                'comment'  => $this->newComment,
            ]);

            $this->successMessage = "Registro {$this->newType} agregado con éxito.";
            $this->reset(['newName', 'newContent', 'newComment', 'newPriority']);
            $this->newName = '@';
            $this->newTtl  = 3600;

            $this->zone->load('records');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startEdit(int $id): void
    {
        $record = DnsRecord::where('id', $id)->where('dns_zone_id', $this->zone->id)->firstOrFail();
        $this->editingId      = $id;
        $this->editName       = $record->name;
        $this->editType       = $record->type;
        $this->editContent    = $record->content;
        $this->editTtl        = $record->ttl;
        $this->editPriority   = $record->priority;
        $this->successMessage = '';
        $this->errorMessage   = '';
    }

    public function saveEdit(DnsService $dnsService): void
    {
        $this->validate([
            'editName'    => 'required|string|max:255',
            'editContent' => 'required|string|max:4096',
            'editTtl'     => 'required|integer|min:60|max:86400',
        ]);

        $record = DnsRecord::where('id', $this->editingId)->where('dns_zone_id', $this->zone->id)->firstOrFail();

        try {
            $dnsService->updateRecord($record, [
                'name'     => $this->editName,
                'type'     => $this->editType,
                'content'  => $this->editContent,
                'ttl'      => $this->editTtl,
                'priority' => $this->editPriority,
            ]);

            $this->successMessage = "Registro actualizado con éxito.";
            $this->editingId = null;
            $this->zone->load('records');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function deleteRecord(int $id, DnsService $dnsService): void
    {
        $record = DnsRecord::where('id', $id)->where('dns_zone_id', $this->zone->id)->firstOrFail();
        try {
            $dnsService->deleteRecord($record);
            $this->successMessage = "Registro DNS eliminado con éxito.";
            $this->zone->load('records');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function updatedNewType(): void
    {
        // Auto-set priority for MX
        if ($this->newType === 'MX') {
            $this->newPriority = 10;
        } else {
            $this->newPriority = 0;
        }
    }

    public function render()
    {
        return view('livewire.dns.dns-zone-editor', [
            'records' => $this->zone->records()->orderBy('type')->orderBy('name')->get(),
        ])->layout('layouts.app', [
            'title'      => 'Editar Zona DNS: ' . $this->zone->name,
            'breadcrumb' => '<span><a href="' . route('dns.index') . '">DNS Manager</a></span> / <strong>' . $this->zone->name . '</strong>',
        ]);
    }
}

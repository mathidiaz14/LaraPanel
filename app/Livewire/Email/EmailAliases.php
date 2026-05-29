<?php

namespace App\Livewire\Email;

use App\Models\EmailAlias;
use App\Models\Domain;
use App\Models\AuditLog;
use Livewire\Component;

class EmailAliases extends Component
{
    // Form
    public ?int   $domainId      = null;
    public string $sourcePrefix  = '';  // part before @
    public bool   $isCatchall    = false;
    public string $destinations  = '';  // comma-separated
    public string $notes         = '';

    // Change dest modal
    public ?int $editingId = null;
    public string $editDestinations = '';

    public string $successMessage = '';
    public string $errorMessage   = '';

    protected array $rules = [
        'domainId'     => 'required|integer|exists:domains,id',
        'destinations' => 'required|string',
        'notes'        => 'nullable|string|max:255',
    ];

    public function createAlias(): void
    {
        $this->validate();
        $this->successMessage = '';
        $this->errorMessage   = '';

        $domain = Domain::where('id', $this->domainId)->where('user_id', auth()->id())->firstOrFail();

        // Parse and validate destinations
        $dests = array_filter(array_map('trim', explode(',', $this->destinations)));
        $validDests = [];
        foreach ($dests as $d) {
            if (!filter_var($d, FILTER_VALIDATE_EMAIL)) {
                $this->errorMessage = "La dirección «{$d}» no es válida.";
                return;
            }
            $validDests[] = $d;
        }

        if (empty($validDests)) {
            $this->errorMessage = "Debe especificar al menos un destino válido.";
            return;
        }

        // Build source address
        $source = $this->isCatchall
            ? '@' . $domain->name
            : strtolower(trim($this->sourcePrefix)) . '@' . $domain->name;

        if (EmailAlias::where('source', $source)->exists()) {
            $this->errorMessage = "El alias {$source} ya existe.";
            return;
        }

        try {
            EmailAlias::create([
                'user_id'      => auth()->id(),
                'domain_id'    => $domain->id,
                'source'       => $source,
                'destinations' => $validDests,
                'is_catchall'  => $this->isCatchall,
                'is_active'    => true,
                'notes'        => $this->notes,
            ]);

            AuditLog::record('email.alias.created', $source, ['destinations' => $validDests]);
            $this->successMessage = "Alias {$source} creado con éxito → " . implode(', ', $validDests);
            $this->reset(['sourcePrefix', 'destinations', 'notes', 'isCatchall', 'domainId']);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openEdit(int $id): void
    {
        $alias = EmailAlias::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->editingId       = $id;
        $this->editDestinations = implode(', ', $alias->destinations ?? []);
    }

    public function saveEdit(): void
    {
        $alias = EmailAlias::where('id', $this->editingId)->where('user_id', auth()->id())->firstOrFail();

        $dests = array_filter(array_map('trim', explode(',', $this->editDestinations)));
        $validDests = [];
        foreach ($dests as $d) {
            if (!filter_var($d, FILTER_VALIDATE_EMAIL)) {
                $this->errorMessage = "La dirección «{$d}» no es válida.";
                return;
            }
            $validDests[] = $d;
        }

        $alias->update(['destinations' => $validDests]);
        $this->successMessage = "Alias actualizado con éxito.";
        $this->editingId = null;
        AuditLog::record('email.alias.updated', $alias->source);
    }

    public function toggleAlias(int $id): void
    {
        $alias = EmailAlias::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $alias->update(['is_active' => !$alias->is_active]);
        $this->successMessage = $alias->is_active ? "Alias activado." : "Alias desactivado.";
    }

    public function deleteAlias(int $id): void
    {
        $alias = EmailAlias::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        AuditLog::record('email.alias.deleted', $alias->source);
        $alias->delete();
        $this->successMessage = "Alias eliminado con éxito.";
    }

    public function render()
    {
        $aliases = EmailAlias::with('domain')
            ->where('user_id', auth()->id())
            ->orderBy('is_catchall', 'desc')
            ->orderBy('source')
            ->get();

        $domains = Domain::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.email.email-aliases', [
            'aliases' => $aliases,
            'domains' => $domains,
        ])->layout('layouts.app', [
            'title'      => 'Aliases de Correo',
            'breadcrumb' => '<span><a href="' . route('email.index') . '">Email</a></span> / <strong>Aliases</strong>',
        ]);
    }
}

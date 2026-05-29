<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use Livewire\Component;

class PlanIndex extends Component
{
    public $plans;
    public bool $isEditing = false;
    
    // Form fields
    public ?int $planId = null;
    public string $name = '';
    public string $description = '';
    public float $price = 0.0;
    public int $max_domains = 1;
    public int $max_subdomains = 0;
    public int $max_email_accounts = 0;
    public int $max_databases = 0;
    public int $max_ftp_accounts = 0;
    public int $disk_quota_bytes = 1073741824; // 1GB
    public int $bandwidth_bytes = 10737418240; // 10GB
    public int $max_cron_jobs = 0;
    public bool $ssl_enabled = true;
    public bool $backups_enabled = false;
    public bool $terminal_enabled = false;
    public bool $is_active = true;

    protected array $rules = [
        'name'               => 'required|string|max:255',
        'description'        => 'nullable|string',
        'price'              => 'numeric|min:0',
        'max_domains'        => 'integer|min:-1',
        'max_subdomains'     => 'integer|min:-1',
        'max_email_accounts' => 'integer|min:-1',
        'max_databases'      => 'integer|min:-1',
        'max_ftp_accounts'   => 'integer|min:-1',
        'disk_quota_bytes'   => 'integer|min:-1',
        'bandwidth_bytes'    => 'integer|min:-1',
        'max_cron_jobs'      => 'integer|min:-1',
        'ssl_enabled'        => 'boolean',
        'backups_enabled'    => 'boolean',
        'terminal_enabled'   => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function mount()
    {
        $this->loadPlans();
    }

    public function loadPlans()
    {
        $this->plans = Plan::withCount('users')->orderBy('price', 'asc')->get();
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = true;
    }

    public function edit(int $id)
    {
        $plan = Plan::findOrFail($id);
        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->price = $plan->price;
        $this->max_domains = $plan->max_domains;
        $this->max_subdomains = $plan->max_subdomains;
        $this->max_email_accounts = $plan->max_email_accounts;
        $this->max_databases = $plan->max_databases;
        $this->max_ftp_accounts = $plan->max_ftp_accounts;
        $this->disk_quota_bytes = $plan->disk_quota_bytes;
        $this->bandwidth_bytes = $plan->bandwidth_bytes;
        $this->max_cron_jobs = $plan->max_cron_jobs;
        $this->ssl_enabled = $plan->ssl_enabled;
        $this->backups_enabled = $plan->backups_enabled;
        $this->terminal_enabled = $plan->terminal_enabled;
        $this->is_active = $plan->is_active;

        $this->isEditing = true;
    }

    public function save()
    {
        $data = $this->validate();
        $data['slug'] = \Illuminate\Support\Str::slug($this->name);

        if ($this->planId) {
            Plan::find($this->planId)->update($data);
            session()->flash('message', 'Plan actualizado correctamente.');
        } else {
            Plan::create($data);
            session()->flash('message', 'Plan creado exitosamente.');
        }

        $this->isEditing = false;
        $this->loadPlans();
    }

    public function resetForm()
    {
        $this->resetExcept('plans');
    }

    public function render()
    {
        return view('livewire.admin.plan-index')->layout('layouts.app', [
            'title'      => 'Planes de Hosting',
            'breadcrumb' => '<span>Admin</span> / <strong>Planes</strong>',
        ]);
    }
}

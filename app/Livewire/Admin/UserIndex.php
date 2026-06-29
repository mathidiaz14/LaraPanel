<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UserIndex extends Component
{
    public $users;
    public $plans;
    
    public bool $isEditing = false;
    public ?int $userId = null;
    
    // Form fields
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'client';
    public ?int $plan_id = null;
    public bool $is_active = true;

    public function rules(): array
    {
        $roles = auth()->user()->isAdmin() ? 'admin,reseller,client' : 'client';
        return [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'role'      => 'required|in:' . $roles,
            'plan_id'   => 'nullable|exists:plans,id',
            'is_active' => 'boolean',
        ];
    }

    public function mount()
    {
        $this->plans = Plan::where('is_active', true)->get();
        $this->loadUsers();
    }

    public function loadUsers()
    {
        $query = User::with('plan')->withCount('domains');
        
        if (auth()->user()->isReseller()) {
            $query->where('parent_id', auth()->id());
        }
        
        $this->users = $query->get();
    }

    public function create()
    {
        $this->resetForm();
        // Default role for resellers is client
        if (auth()->user()->isReseller()) {
            $this->role = 'client';
        }
        $this->isEditing = true;
    }

    public function edit(int $id)
    {
        $query = User::query();
        if (auth()->user()->isReseller()) {
            $query->where('parent_id', auth()->id());
        }
        
        $user = $query->findOrFail($id);
        $this->userId    = $user->id;
        $this->name      = $user->name;
        $this->email     = $user->email;
        $this->role      = $user->role;
        $this->plan_id   = $user->plan_id;
        $this->is_active = $user->is_active;
        $this->password  = '';

        $this->isEditing = true;
    }

    public function save()
    {
        $data = $this->validate();

        // Check email uniqueness manually to allow updating current user
        $existing = User::where('email', $this->email)->first();
        if ($existing && $existing->id !== $this->userId) {
            $this->addError('email', 'Este email ya está en uso.');
            return;
        }

        if (empty($data['plan_id'])) {
            $data['plan_id'] = null;
        }

        if (auth()->user()->isReseller()) {
            $data['parent_id'] = auth()->id();
            $data['role'] = 'client';
        }

        if ($this->userId) {
            $query = User::query();
            if (auth()->user()->isReseller()) {
                $query->where('parent_id', auth()->id());
            }
            $user = $query->findOrFail($this->userId);
            
            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            } else {
                unset($data['password']);
            }
            
            $user->update($data);
            
            // Suspension logic
            if (!$this->is_active && !$user->isSuspended()) {
                $user->suspended_at = now();
                $user->suspension_reason = 'Suspendido manualmente.';
                $user->save();
                // TODO: Dispatch Job to disable Nginx vhosts for all domains belonging to this user
            } elseif ($this->is_active && $user->isSuspended()) {
                $user->suspended_at = null;
                $user->suspension_reason = null;
                $user->save();
                // TODO: Dispatch Job to re-enable Nginx vhosts
            }
            
            session()->flash('message', 'Usuario actualizado.');
        } else {
            if (empty($this->password)) {
                $this->addError('password', 'La contraseña es obligatoria para nuevos usuarios.');
                return;
            }
            $data['password'] = Hash::make($this->password);
            User::create($data);
            session()->flash('message', 'Usuario creado exitosamente.');
        }

        $this->isEditing = false;
        $this->loadUsers();
    }

    public function suspend(int $id)
    {
        $query = User::query();
        if (auth()->user()->isReseller()) {
            $query->where('parent_id', auth()->id());
        }
        
        $user = $query->findOrFail($id);
        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes suspenderte a ti mismo.');
            return;
        }
        
        $user->is_active = false;
        $user->suspended_at = now();
        $user->suspension_reason = 'Suspendido rápidamente desde el panel.';
        $user->save();
        $this->loadUsers();
        session()->flash('message', 'Usuario suspendido.');
    }

    public function activate(int $id)
    {
        $query = User::query();
        if (auth()->user()->isReseller()) {
            $query->where('parent_id', auth()->id());
        }
        
        $user = $query->findOrFail($id);
        $user->is_active = true;
        $user->suspended_at = null;
        $user->save();
        $this->loadUsers();
        session()->flash('message', 'Usuario activado.');
    }

    public function resetForm()
    {
        $this->reset(['userId', 'name', 'email', 'password', 'role', 'plan_id', 'is_active']);
    }

    public function render()
    {
        return view('livewire.admin.user-index')->layout('layouts.app', [
            'title'      => 'Gestión de Usuarios',
            'breadcrumb' => '<span>Admin</span> / <strong>Usuarios</strong>',
        ]);
    }
}

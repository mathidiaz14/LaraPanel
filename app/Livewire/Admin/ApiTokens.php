<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class ApiTokens extends Component
{
    public $tokens;
    public string $tokenName = '';
    public ?string $plainTextToken = null;

    protected array $rules = [
        'tokenName' => 'required|string|max:255',
    ];

    public function mount()
    {
        $this->loadTokens();
    }

    public function loadTokens()
    {
        // Admin tokens only for WHMCS billing integration
        $this->tokens = Auth::user()->tokens()->orderByDesc('created_at')->get();
    }

    public function createToken()
    {
        $this->validate();
        
        $token = Auth::user()->createToken($this->tokenName, ['*']);
        $this->plainTextToken = $token->plainTextToken;
        $this->tokenName = '';
        
        $this->loadTokens();
        session()->flash('message', 'Token generado exitosamente.');
    }

    public function revokeToken(int $tokenId)
    {
        Auth::user()->tokens()->where('id', $tokenId)->delete();
        $this->loadTokens();
        $this->plainTextToken = null;
        session()->flash('message', 'Token revocado.');
    }

    public function render()
    {
        return view('livewire.admin.api-tokens')->layout('layouts.app', [
            'title'      => 'Tokens API',
            'breadcrumb' => '<span>Admin</span> / <span>Configuración</span> / <strong>API Tokens</strong>',
        ]);
    }
}

<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\UpdateUserProfileInformation;
use Laravel\Fortify\Actions\UpdateUserPassword;
use Laravel\Fortify\Features;

class Profile extends Component
{
    public string $name = '';
    public string $email = '';
    
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    
    public string $code = '';
    
    public bool $showingQrCode = false;
    public bool $showingConfirmation = false;
    public bool $showingRecoveryCodes = false;

    public function mount()
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(UpdateUserProfileInformation $updater)
    {
        $updater->update(Auth::user(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);
        
        $this->dispatch('toast', message: 'Perfil actualizado correctamente.', type: 'success');
    }

    public function updatePassword(UpdateUserPassword $updater)
    {
        $this->resetErrorBag();
        
        try {
            $updater->update(Auth::user(), [
                'current_password' => $this->current_password,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
            
            $this->current_password = '';
            $this->password = '';
            $this->password_confirmation = '';
            
            $this->dispatch('toast', message: 'Contraseña actualizada correctamente.', type: 'success');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }
    }

    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable)
    {
        $enable(Auth::user());
        $this->showingQrCode = true;
        
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            $this->showingConfirmation = true;
        } else {
            $this->showingRecoveryCodes = true;
        }
        
        Auth::user()->update(['two_factor_enabled' => true]);
    }

    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm)
    {
        try {
            $confirm(Auth::user(), $this->code);
            $this->showingQrCode = false;
            $this->showingConfirmation = false;
            $this->showingRecoveryCodes = true;
            $this->code = '';
            
            $this->dispatch('toast', message: 'Autenticación de dos factores activada.', type: 'success');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }
    }

    public function showRecoveryCodes()
    {
        $this->showingRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate)
    {
        $generate(Auth::user());
        $this->showingRecoveryCodes = true;
        $this->dispatch('toast', message: 'Códigos de recuperación regenerados.', type: 'success');
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable)
    {
        $disable(Auth::user());
        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = false;
        
        Auth::user()->update(['two_factor_enabled' => false]);
        
        $this->dispatch('toast', message: 'Autenticación de dos factores deshabilitada.', type: 'success');
    }
    
    public function getUserProperty()
    {
        return Auth::user();
    }

    public function render()
    {
        return view('livewire.profile')
            ->layout('layouts.app', [
                'title' => 'Mi Perfil',
                'breadcrumb' => '<strong>Mi Perfil</strong>',
            ]);
    }
}

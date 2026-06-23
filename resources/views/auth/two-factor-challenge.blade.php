@extends('layouts.auth')
@section('title', 'Autenticación de Dos Factores')

@section('content')
<div class="glass-elevated auth-card" x-data="{ recovery: false }">
    <div class="auth-logo">
        <div class="logo-icon" style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;box-shadow:0 0 30px rgba(99,102,241,0.35);margin:0 auto 14px;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="auth-title">Seguridad 2FA</div>
        <div class="auth-sub" x-show="!recovery">Confirma acceso con tu app de autenticación</div>
        <div class="auth-sub" x-show="recovery" style="display: none;">Confirma acceso usando un código de recuperación</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:6px"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login') }}">
        @csrf
        
        <div class="form-group" x-show="!recovery">
            <label class="form-label" for="code">Código de Autenticación</label>
            <input id="code" type="text" inputmode="numeric" name="code" class="form-input"
                   placeholder="123456" autofocus autocomplete="one-time-code">
        </div>

        <div class="form-group" x-show="recovery" style="display: none;">
            <label class="form-label" for="recovery_code">Código de Recuperación</label>
            <input id="recovery_code" type="text" name="recovery_code" class="form-input"
                   placeholder="abcdef-98765" autocomplete="one-time-code">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px;margin-bottom:18px;">
            <i class="fa-solid fa-unlock-keyhole"></i>
            Verificar Acceso
        </button>

        <div style="text-align:center;">
            <button type="button" class="btn btn-ghost" x-show="!recovery" x-on:click="recovery = true; setTimeout(() => $refs.recovery_code.focus(), 50);" style="font-size:13px;width:100%;justify-content:center;">
                Usar un código de recuperación
            </button>
            <button type="button" class="btn btn-ghost" x-show="recovery" x-on:click="recovery = false; setTimeout(() => $refs.code.focus(), 50);" style="font-size:13px;width:100%;justify-content:center;display:none;">
                Usar código de aplicación (TOTP)
            </button>
        </div>
    </form>
</div>
@endsection

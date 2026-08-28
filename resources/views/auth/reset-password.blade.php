@extends('layouts.auth')
@section('title', 'Restablecer Contraseña')

@section('content')
<div class="glass-elevated auth-card">
    <div class="auth-logo">
        <div class="logo-icon" style="width:52px;height:52px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--accent),#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:var(--text-xl);font-weight:700;color:#fff;box-shadow:0 0 30px var(--accent-glow);margin:0 auto var(--sp-3);">
            <i class="fa-solid fa-lock-open"></i>
        </div>
        <div class="auth-title">Nueva Contraseña</div>
        <div class="auth-sub">Elegí una contraseña segura para tu cuenta</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:var(--sp-2)"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="form-input"
                   value="{{ old('email', $request->email) }}" placeholder="admin@tuservidor.com"
                   required autofocus autocomplete="email">
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Nueva Contraseña</label>
            <input id="password" type="password" name="password" class="form-input"
                   placeholder="••••••••" required autocomplete="new-password">
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirmar Contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-input"
                   placeholder="••••••••" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:var(--sp-3);margin-bottom:var(--sp-4);">
            <i class="fa-solid fa-shield-check"></i>
            Restablecer Contraseña
        </button>
    </form>

    <div style="margin-top:var(--sp-6);padding-top:var(--sp-6);border-top:1px solid var(--glass-border);text-align:center;">
        <p style="font-size:var(--text-sm);color:var(--text-muted);">
            <i class="fa-solid fa-shield-halved" style="color:var(--success);margin-right:var(--sp-1);"></i>
            Conexión cifrada · LaraPanel v{{ config('larapanel.version') }}
        </p>
    </div>
</div>
@endsection

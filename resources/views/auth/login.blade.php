@extends('layouts.auth')
@section('title', 'Iniciar Sesión')

@section('content')
<div class="glass-elevated auth-card">
    <div class="auth-logo">
        <div class="logo-icon" style="width:52px;height:52px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--accent),#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:var(--text-xl);font-weight:700;color:#fff;box-shadow:0 0 30px var(--accent-glow);margin:0 auto var(--sp-3);">LP</div>
        <div class="auth-title">LaraPanel</div>
        <div class="auth-sub">Inicia sesión para gestionar tu servidor</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:var(--sp-2)"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="form-input"
                   value="{{ old('email') }}" placeholder="admin@tuservidor.com"
                   required autofocus autocomplete="email">
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <input id="password" type="password" name="password" class="form-input"
                   placeholder="••••••••" required autocomplete="current-password">
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-6);">
            <label style="display:flex;align-items:center;gap:var(--sp-2);font-size:var(--text-sm);color:var(--text-secondary);cursor:pointer;">
                <input type="checkbox" name="remember" style="accent-color:var(--accent);">
                Recordarme
            </label>
            @if(Route::has('password.request'))
            <a href="{{ route('password.request') }}" style="font-size:var(--text-sm);color:var(--accent-light);text-decoration:none;">
                ¿Olvidaste tu contraseña?
            </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:var(--sp-3);">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Iniciar Sesión
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

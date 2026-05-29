@extends('layouts.auth')
@section('title', 'Iniciar Sesión')

@section('content')
<div class="glass-elevated auth-card">
    <div class="auth-logo">
        <div class="logo-icon" style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;box-shadow:0 0 30px rgba(99,102,241,0.35);margin:0 auto 14px;">LP</div>
        <div class="auth-title">LaraPanel</div>
        <div class="auth-sub">Inicia sesión para gestionar tu servidor</div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:6px"></i>
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

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;">
            <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:var(--text-secondary);cursor:pointer;">
                <input type="checkbox" name="remember" style="accent-color:var(--accent);">
                Recordarme
            </label>
            @if(Route::has('password.request'))
            <a href="{{ route('password.request') }}" style="font-size:13px;color:var(--accent-light);text-decoration:none;">
                ¿Olvidaste tu contraseña?
            </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Iniciar Sesión
        </button>
    </form>

    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--glass-border);text-align:center;">
        <p style="font-size:12px;color:var(--text-muted);">
            <i class="fa-solid fa-shield-halved" style="color:var(--success);margin-right:4px;"></i>
            Conexión cifrada · LaraPanel v{{ config('larapanel.version') }}
        </p>
    </div>
</div>
@endsection

@extends('layouts.auth')
@section('title', 'Recuperar Contraseña')

@section('content')
<div class="glass-elevated auth-card">
    <div class="auth-logo">
        <div class="logo-icon" style="width:52px;height:52px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--accent),#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:var(--text-xl);font-weight:700;color:#fff;box-shadow:0 0 30px var(--accent-glow);margin:0 auto var(--sp-3);">
            <i class="fa-solid fa-key"></i>
        </div>
        <div class="auth-title">Recuperar Contraseña</div>
        <div class="auth-sub">Ingresá tu email y te enviamos un enlace para restablecer tu contraseña</div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:var(--sp-3) var(--sp-4);margin-bottom:var(--sp-6);display:flex;align-items:center;gap:var(--sp-2);font-size:var(--text-base);color:var(--success);">
            <i class="fa-solid fa-circle-check"></i>
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:var(--sp-2)"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="form-input"
                   value="{{ old('email') }}" placeholder="admin@tuservidor.com"
                   required autofocus autocomplete="email">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:var(--sp-3);margin-bottom:var(--sp-4);">
            <i class="fa-solid fa-paper-plane"></i>
            Enviar Enlace de Recuperación
        </button>
    </form>

    <div style="text-align:center;">
        <a href="{{ route('login') }}" class="btn btn-ghost" style="font-size:var(--text-sm);width:100%;justify-content:center;">
            <i class="fa-solid fa-arrow-left"></i>
            Volver al inicio de sesión
        </a>
    </div>

    <div style="margin-top:var(--sp-6);padding-top:var(--sp-6);border-top:1px solid var(--glass-border);text-align:center;">
        <p style="font-size:var(--text-sm);color:var(--text-muted);">
            <i class="fa-solid fa-shield-halved" style="color:var(--success);margin-right:var(--sp-1);"></i>
            Conexión cifrada · LaraPanel v{{ config('larapanel.version') }}
        </p>
    </div>
</div>
@endsection

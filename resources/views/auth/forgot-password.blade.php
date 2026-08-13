@extends('layouts.auth')
@section('title', 'Recuperar Contraseña')

@section('content')
<div class="glass-elevated auth-card">
    <div class="auth-logo">
        <div class="logo-icon" style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;box-shadow:0 0 30px rgba(99,102,241,0.35);margin:0 auto 14px;">
            <i class="fa-solid fa-key"></i>
        </div>
        <div class="auth-title">Recuperar Contraseña</div>
        <div class="auth-sub">Ingresá tu email y te enviamos un enlace para restablecer tu contraseña</div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:14px;color:#4ade80;">
            <i class="fa-solid fa-circle-check"></i>
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:6px"></i>
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

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px;margin-bottom:18px;">
            <i class="fa-solid fa-paper-plane"></i>
            Enviar Enlace de Recuperación
        </button>
    </form>

    <div style="text-align:center;">
        <a href="{{ route('login') }}" class="btn btn-ghost" style="font-size:13px;width:100%;justify-content:center;">
            <i class="fa-solid fa-arrow-left"></i>
            Volver al inicio de sesión
        </a>
    </div>

    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--glass-border);text-align:center;">
        <p style="font-size:12px;color:var(--text-muted);">
            <i class="fa-solid fa-shield-halved" style="color:var(--success);margin-right:4px;"></i>
            Conexión cifrada · LaraPanel v{{ config('larapanel.version') }}
        </p>
    </div>
</div>
@endsection

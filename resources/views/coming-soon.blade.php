@extends('layouts.app')
@section('title', $module)
@section('breadcrumb')
<strong>{{ $module }}</strong>
@endsection

@section('content')
<div class="glass" style="padding:60px 40px;text-align:center;max-width:600px;margin:40px auto;">
    <div style="font-size:52px;margin-bottom:20px;opacity:0.4;">
        <i class="fa-solid fa-hammer"></i>
    </div>
    <h1 style="font-size:22px;font-weight:700;margin-bottom:10px;">{{ $module }}</h1>
    <p style="color:var(--text-secondary);margin-bottom:28px;line-height:1.7;">
        Este módulo está en desarrollo activo. Será parte de la <strong>Fase 1</strong> de LaraPanel.
        El dashboard principal ya está funcional con métricas en tiempo real.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;">
        <a href="{{ route('dashboard') }}" class="btn btn-primary">
            <i class="fa-solid fa-gauge-high"></i> Ir al Dashboard
        </a>
        <a href="https://github.com/tu-usuario/larapanel" target="_blank" class="btn btn-ghost">
            <i class="fa-brands fa-github"></i> Ver en GitHub
        </a>
    </div>
    <div style="margin-top:32px;padding-top:20px;border-top:1px solid var(--glass-border);">
        <div style="font-size:12px;color:var(--text-muted);">Roadmap de módulos:</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:10px;">
            @foreach(['Dominios','SSL','Email','MySQL','Archivos','DNS','Firewall','Cron','Backups','FTP','Terminal'] as $m)
            <span class="badge {{ $m === $module ? 'badge-accent' : 'badge-muted' }}">{{ $m }}</span>
            @endforeach
        </div>
    </div>
</div>
@endsection

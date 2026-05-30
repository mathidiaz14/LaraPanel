<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — LaraPanel</title>
    <meta name="description" content="@yield('meta_description', 'LaraPanel — Servidor web management panel')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/larapanel.css') }}">
    @livewireStyles
    @stack('styles')
</head>
<body>
<div class="app-layout">

    {{-- ── Sidebar ─────────────────────────────────── --}}
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">LP</div>
            <div>
                <div class="logo-text">LaraPanel</div>
                <div class="logo-version">v{{ config('larapanel.version') }}</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Principal</div>

            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
                Dashboard
            </a>

            @if(config('larapanel.modules.domains'))
            <div class="nav-section-title">Hosting</div>
            <a href="{{ route('domains.index') }}" class="nav-item {{ request()->routeIs('domains.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-globe"></i></span>
                Dominios
            </a>
            <a href="{{ route('ssl.index') }}" class="nav-item {{ request()->routeIs('ssl.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-lock"></i></span>
                SSL / TLS
            </a>
            @endif

            @if(config('larapanel.modules.email'))
            <a href="{{ route('email.index') }}" class="nav-item {{ request()->routeIs('email.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-envelope"></i></span>
                Email
            </a>
            @endif

            @if(config('larapanel.modules.databases'))
            <a href="{{ route('databases.index') }}" class="nav-item {{ request()->routeIs('databases.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-database"></i></span>
                Bases de Datos
            </a>
            @endif

            @if(config('larapanel.modules.filemanager'))
            <a href="{{ route('files.index') }}" class="nav-item {{ request()->routeIs('files.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-folder-open"></i></span>
                Archivos
            </a>
            @endif

            @if(config('larapanel.modules.ftp'))
            <a href="{{ route('ftp.index') }}" class="nav-item {{ request()->routeIs('ftp.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-server"></i></span>
                FTP
            </a>
            @endif

            @if(config('larapanel.modules.dns'))
            <div class="nav-section-title">Avanzado</div>
            <a href="{{ route('dns.index') }}" class="nav-item {{ request()->routeIs('dns.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-sitemap"></i></span>
                DNS Manager
            </a>
            @endif

            @if(config('larapanel.modules.firewall'))
            <a href="{{ route('firewall.index') }}" class="nav-item {{ request()->routeIs('firewall.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                Firewall
            </a>
            @endif

            <a href="{{ route('fail2ban.index') }}" class="nav-item {{ request()->routeIs('fail2ban.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-ban"></i></span>
                Fail2ban
            </a>

            <a href="{{ route('antispam.index') }}" class="nav-item {{ request()->routeIs('antispam.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-shield-virus"></i></span>
                Antispam
            </a>

            @if(config('larapanel.modules.antivirus'))
            <a href="{{ route('antivirus.index') }}" class="nav-item {{ request()->routeIs('antivirus.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                Antivirus
            </a>
            @endif

            @if(config('larapanel.modules.cron'))
            <a href="{{ route('cron.index') }}" class="nav-item {{ request()->routeIs('cron.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                Cron Jobs
            </a>
            @endif

            @if(config('larapanel.modules.backups'))
            <a href="{{ route('backups.index') }}" class="nav-item {{ request()->routeIs('backups.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-hard-drive"></i></span>
                Backups
            </a>
            @endif

            <a href="{{ route('git.index') }}" class="nav-item {{ request()->routeIs('git.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-brands fa-git-alt"></i></span>
                Git Deploy
            </a>

            @if(config('larapanel.modules.docker'))
            <a href="{{ route('docker.index') }}" class="nav-item {{ request()->routeIs('docker.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-brands fa-docker"></i></span>
                Docker
            </a>
            @endif

            <a href="{{ route('wordpress.index') }}" class="nav-item {{ request()->routeIs('wordpress.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-brands fa-wordpress"></i></span>
                WordPress
            </a>

            @if(config('larapanel.modules.terminal') && auth()->user()?->isAdmin())
            <a href="{{ route('terminal.index') }}" class="nav-item {{ request()->routeIs('terminal.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-terminal"></i></span>
                Terminal
                <span class="nav-badge">Admin</span>
            </a>
            @endif

            @if(config('larapanel.modules.monitoring') || config('larapanel.modules.phpmanager') || config('larapanel.modules.logs'))
            <div class="nav-section-title">Sistema</div>
            @endif

            @if(config('larapanel.modules.phpmanager'))
            <a href="{{ route('php.index') }}" class="nav-item {{ request()->routeIs('php.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-brands fa-php"></i></span>
                PHP Manager
            </a>
            @endif

            @if(config('larapanel.modules.logs'))
            <a href="{{ route('logs.index') }}" class="nav-item {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-scroll"></i></span>
                Logs
            </a>
            @endif

            @if(auth()->user()?->isAdmin())
            <div class="nav-section-title">Administración</div>
            <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                Usuarios
            </a>
            <a href="{{ route('admin.plans.index') }}" class="nav-item {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span>
                Planes
            </a>
            <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                Configuración
            </a>
            <a href="{{ route('admin.api-tokens') }}" class="nav-item {{ request()->routeIs('admin.api-tokens') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-key"></i></span>
                Tokens API
            </a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <a href="{{ route('profile') }}" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-circle-user"></i></span>
                Mi Perfil
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item" style="width:100%;background:none;text-align:left;">
                    <span class="nav-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Topbar ──────────────────────────────────── --}}
    <header class="topbar">
        <button class="topbar-btn" id="sidebar-toggle" style="display:none">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-breadcrumb">
            {!! $breadcrumb ?? $__env->yieldContent('breadcrumb', '<strong>Dashboard</strong>') !!}
        </div>
        <div class="topbar-actions">
            <button class="topbar-btn" title="Notificaciones">
                <i class="fa-solid fa-bell"></i>
            </button>
            <div class="user-chip">
                <div class="user-avatar">{{ substr(auth()->user()?->name ?? 'A', 0, 1) }}</div>
                <span class="user-name">{{ auth()->user()?->name ?? 'Admin' }}</span>
            </div>
        </div>
    </header>

    {{-- ── Main Content ────────────────────────────── --}}
    <main class="main-content">
        <div class="page">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if(isset($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@livewireScripts
@stack('scripts')
<script>
    // Mobile sidebar toggle
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    if (window.innerWidth <= 768 && toggle) toggle.style.display = 'flex';
</script>
</body>
</html>

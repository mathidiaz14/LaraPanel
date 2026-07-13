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
    <style>
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7);
            }

            70% {
                transform: scale(1.1);
                opacity: 0.8;
                box-shadow: 0 0 0 6px rgba(251, 191, 36, 0);
            }

            100% {
                transform: scale(1);
                opacity: 1;
                box-shadow: 0 0 0 0 rgba(251, 191, 36, 0);
            }
        }
    </style>
</head>

<body>
    @if(session()->has('impersonated_by'))
        <div style="background:#f59e0b;color:#1e1e2e;padding:10px 20px;font-size:13px;display:flex;justify-content:space-between;align-items:center;z-index:99999;box-shadow:0 2px 10px rgba(0,0,0,0.3);position:sticky;top:0;font-weight:500;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-user-secret" style="font-size:16px;"></i>
                <span>Estás en una sesión de impersonación como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</span>
            </div>
            <a href="{{ route('admin.impersonate.stop') }}" class="btn btn-primary btn-sm" style="background:#1e1e2e;color:#fff;border:none;padding:5px 12px;font-weight:600;border-radius:4px;text-decoration:none;font-size:12px;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;">
                <i class="fa-solid fa-right-from-bracket"></i> Volver a mi cuenta
            </a>
        </div>
    @endif

    <div class="app-layout">

        <div id="sidebar-overlay"></div>

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
                {{-- Remote server banner indicator --}}
                @if(App\Shell\ServerContext::isRemote())
                    <div
                        style="background:rgba(137,180,250,0.1);border:1px solid rgba(137,180,250,0.25);border-radius:8px;padding:8px 12px;margin:12px;font-size:11px;color:#89b4fa;display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-server fa-pulse"></i>
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ App\Shell\ServerContext::server()->name }}
                            </div>
                            <div style="font-size:9px;opacity:0.7;">Modo Remoto Activo</div>
                        </div>
                    </div>
                @endif

                <div class="nav-section-title">Principal</div>

                <a href="{{ route('dashboard') }}"
                    class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
                    Dashboard
                </a>

                @if(config('larapanel.modules.domains'))
                    <div class="nav-section-title">Hosting</div>
                    <a href="{{ route('domains.index') }}"
                        class="nav-item {{ request()->routeIs('domains.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-globe"></i></span>
                        Dominios
                    </a>
                    <a href="{{ route('ssl.index') }}" class="nav-item {{ request()->routeIs('ssl.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-lock"></i></span>
                        SSL / TLS
                    </a>
                @endif

                @if(config('larapanel.modules.email'))
                    <a href="{{ route('email.index') }}"
                        class="nav-item {{ request()->routeIs('email.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-envelope"></i></span>
                        Email
                    </a>
                @endif

                @if(config('larapanel.modules.databases'))
                    <a href="{{ route('databases.index') }}"
                        class="nav-item {{ request()->routeIs('databases.index') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-database"></i></span>
                        Bases de Datos
                    </a>
                    @if(auth()->user()?->isAdmin())
                        <a href="{{ route('admin.db') }}" target="_blank"
                            class="nav-item {{ request()->routeIs('admin.db') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-table-list"></i></span>
                            phpMyAdmin
                        </a>
                    @endif
                @endif

                @if(config('larapanel.modules.filemanager'))
                    <a href="{{ route('files.index') }}"
                        class="nav-item {{ request()->routeIs('files.*') ? 'active' : '' }}">
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

                @if(auth()->user()?->isAdmin())
                    @if(config('larapanel.modules.firewall'))
                        <a href="{{ route('firewall.index') }}"
                            class="nav-item {{ request()->routeIs('firewall.*') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                            Firewall
                        </a>
                        <a href="{{ route('uptime.index') }}"
                            class="nav-item {{ request()->routeIs('uptime.*') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                            Monitor Uptime
                        </a>
                        <a href="{{ route('performance.index') }}"
                            class="nav-item {{ request()->routeIs('performance.*') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                            Performance & WAF
                        </a>
                    @endif

                    <a href="{{ route('fail2ban.index') }}"
                        class="nav-item {{ request()->routeIs('fail2ban.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-ban"></i></span>
                        Fail2ban
                    </a>

                    <a href="{{ route('antispam.index') }}"
                        class="nav-item {{ request()->routeIs('antispam.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-shield-virus"></i></span>
                        Antispam
                    </a>

                    @if(config('larapanel.modules.antivirus'))
                        <a href="{{ route('antivirus.index') }}"
                            class="nav-item {{ request()->routeIs('antivirus.*') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                            Antivirus
                        </a>
                    @endif
                @endif

                @if(config('larapanel.modules.cron'))
                    <a href="{{ route('cron.index') }}" class="nav-item {{ request()->routeIs('cron.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                        Cron Jobs
                    </a>
                @endif

                @if(config('larapanel.modules.backups'))
                    <a href="{{ route('backups.index') }}"
                        class="nav-item {{ request()->routeIs('backups.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-hard-drive"></i></span>
                        Backups
                    </a>
                @endif

                <a href="{{ route('git.index') }}" class="nav-item {{ request()->routeIs('git.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-brands fa-git-alt"></i></span>
                    Git Deploy
                </a>

                @if(config('larapanel.modules.docker') && auth()->user()?->isAdmin())
                    <a href="{{ route('docker.index') }}"
                        class="nav-item {{ request()->routeIs('docker.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-brands fa-docker"></i></span>
                        Docker
                    </a>
                @endif

                @if(config('larapanel.modules.multiserver') && auth()->user()?->isAdmin())
                    <a href="{{ route('servers.index') }}"
                        class="nav-item {{ request()->routeIs('servers.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-server"></i></span>
                        Servidores
                    </a>
                @endif

                <a href="{{ route('wordpress.index') }}"
                    class="nav-item {{ request()->routeIs('wordpress.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-brands fa-wordpress"></i></span>
                    WordPress
                </a>

                @if(config('larapanel.modules.terminal') && auth()->user()?->isAdmin())
                    <a href="{{ route('terminal.index') }}"
                        class="nav-item {{ request()->routeIs('terminal.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-terminal"></i></span>
                        Terminal
                    </a>
                @endif

                @if(auth()->user()?->isAdmin())
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
                @endif

                @if(auth()->user()?->isAdmin() || auth()->user()?->isReseller())
                    <div class="nav-section-title">Administración</div>
                    <a href="{{ route('admin.users.index') }}"
                        class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                        Usuarios
                    </a>
                @endif

                @if(auth()->user()?->isAdmin())
                    <a href="{{ route('admin.plans.index') }}"
                        class="nav-item {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span>
                        Planes
                    </a>
                    <a href="{{ route('admin.settings') }}"
                        class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                        Configuración
                        @if(\App\Services\UpdateService::isUpdateAvailableCached())
                            <span title="Actualización de LaraPanel disponible"
                                style="width:8px;height:8px;background:#fbbf24;border-radius:50%;box-shadow:0 0 10px #fbbf24;display:inline-block;margin-right:12px;animation:pulse 2s infinite;"></span>
                        @endif
                    </a>
                    <a href="{{ route('admin.api-tokens') }}"
                        class="nav-item {{ request()->routeIs('admin.api-tokens') ? 'active' : '' }}">
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
            <button class="topbar-btn sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-breadcrumb">
                {!! $breadcrumb ?? $__env->yieldContent('breadcrumb', '<strong>Dashboard</strong>') !!}
            </div>
            <div class="topbar-actions">
                @if(config('larapanel.modules.multiserver'))
                    <div class="hide-mobile">
                        <livewire:servers.server-selector />
                    </div>
                @endif
                <button class="topbar-btn" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i>
                </button>
                <div class="user-chip">
                    <div class="user-avatar">{{ substr(auth()->user()?->name ?? 'A', 0, 1) }}</div>
                    <span class="user-name hide-mobile">{{ auth()->user()?->name ?? 'Admin' }}</span>
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
        const overlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            } else {
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }

        if (toggle) toggle.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', toggleSidebar);

        // Also close on any nav link click on mobile
        document.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                    toggleSidebar();
                }
            });
        });
        
        // Handle resize events
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    </script>
</body>

</html>
<div
    class="notification-bell"
    wire:poll.15s="refresh"
    x-data="{ open: @entangle('open') }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="topbar-btn notification-trigger"
        title="Notificaciones"
        aria-label="Notificaciones"
        :aria-expanded="open ? 'true' : 'false'"
        @click="open = !open"
    >
        <i class="fa-solid fa-bell"></i>
        @if($unreadCount > 0)
            <span class="notification-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div class="notification-dropdown glass-elevated" x-show="open" x-cloak>
        <div class="notification-header">
            <div>
                <strong>Notificaciones</strong>
                <span>{{ $unreadCount }} sin leer</span>
            </div>
            @if($unreadCount > 0)
                <button type="button" class="btn btn-ghost btn-sm" wire:click="markAllAsRead">
                    <i class="fa-solid fa-check-double"></i> Marcar todas
                </button>
            @endif
        </div>

        <div class="notification-list">
            @forelse($notifications as $notification)
                @php
                    $icon = match($notification->type) {
                        'login', 'login_new_device', 'login_failed' => 'fa-solid fa-shield-halved',
                        'disk_threshold', 'ram_threshold' => 'fa-solid fa-gauge-high',
                        'uptime_down' => 'fa-solid fa-heart-circle-bolt',
                        'backup_failed', 'backup_completed' => 'fa-solid fa-database',
                        'domain_changed' => 'fa-solid fa-globe',
                        'ssl_expiring' => 'fa-solid fa-lock',
                        'update_available' => 'fa-solid fa-arrow-rotate-up',
                        'user_created' => 'fa-solid fa-user-plus',
                        'test' => 'fa-solid fa-flask',
                        default => 'fa-solid fa-bell',
                    };
                @endphp
                <div class="notification-item {{ $notification->read_at ? '' : 'unread' }}">
                    <span class="notification-icon"><i class="{{ $icon }}"></i></span>
                    <div class="notification-body">
                        <div class="notification-title">{{ $notification->title }}</div>
                        <div class="notification-message">{{ $notification->message }}</div>
                        <div class="notification-meta">
                            <span>{{ $notification->created_at->diffForHumans() }}</span>
                            @if($notification->action_url)
                                <a href="{{ $notification->action_url }}">Abrir <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                            @endif
                        </div>
                    </div>
                    <div class="notification-actions">
                        @unless($notification->read_at)
                            <button type="button" wire:click="markAsRead({{ $notification->id }})" title="Marcar como leído" aria-label="Marcar como leído">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        @endunless
                        <button type="button" wire:click="delete({{ $notification->id }})" title="Eliminar" aria-label="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="notification-empty">
                    <i class="fa-solid fa-bell-slash"></i>
                    <span>No hay notificaciones por ahora.</span>
                </div>
            @endforelse
        </div>

        <div class="notification-footer">
            <button type="button" class="btn btn-ghost btn-sm" wire:click="sendTest">
                <i class="fa-solid fa-flask"></i> Probar bandeja
            </button>
            <button type="button" class="btn btn-ghost btn-sm" wire:click="clearRead">
                <i class="fa-solid fa-broom"></i> Limpiar leídas
            </button>
            <a class="btn btn-ghost btn-sm" href="{{ auth()->user()?->isAdmin() ? route('admin.settings') : route('dashboard') }}">
                <i class="fa-solid fa-gear"></i> Ajustes
            </a>
        </div>
    </div>
</div>

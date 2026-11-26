<?php

namespace App\Livewire\Notifications;

use App\Models\PanelNotification;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->loadUnreadCount();
    }

    public function loadUnreadCount(): void
    {
        $this->unreadCount = PanelNotification::where('user_id', auth()->id())
            ->unread()
            ->count();
    }

    public function refresh(): void
    {
        $this->loadUnreadCount();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->loadUnreadCount();
    }

    public function markAsRead(int $notificationId): void
    {
        PanelNotification::where('user_id', auth()->id())
            ->whereKey($notificationId)
            ->get()
            ->each->markRead();

        $this->loadUnreadCount();
    }

    public function markAllAsRead(): void
    {
        PanelNotification::where('user_id', auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        $this->loadUnreadCount();
    }

    public function delete(int $notificationId): void
    {
        PanelNotification::where('user_id', auth()->id())
            ->whereKey($notificationId)
            ->delete();

        $this->loadUnreadCount();
    }

    public function clearRead(): void
    {
        PanelNotification::where('user_id', auth()->id())
            ->whereNotNull('read_at')
            ->delete();

        $this->loadUnreadCount();
    }

    public function sendTest(): void
    {
        if (! auth()->user()) {
            return;
        }

        PanelNotification::forUsers([auth()->user()], [
            'type' => 'test',
            'title' => 'Notificación de prueba',
            'message' => 'La bandeja de notificaciones del panel está funcionando correctamente.',
            'action_url' => route('dashboard'),
        ]);

        $this->open = true;
        $this->loadUnreadCount();
    }

    public function render()
    {
        $notifications = PanelNotification::where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.notifications.notification-bell', [
            'notifications' => $notifications,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Setting;

class NotificationPreferences
{
    /**
     * Catalogue of all notifiable event types.
     *
     * Each entry: key => human-readable label used in the settings UI.
     */
    public const TYPES = [
        'login'             => 'Inicio de sesión exitoso',
        'login_new_device'  => 'Inicio de sesión desde nueva IP/dispositivo',
        'login_failed'      => 'Intentos de login fallidos',
        'disk_threshold'    => 'Umbral de disco superado',
        'ram_threshold'     => 'Umbral de RAM superado',
        'uptime_down'       => 'Uptime o dominio caído',
        'backup_failed'     => 'Backup fallido',
        'backup_completed'  => 'Backup completado',
        'update_available'  => 'Actualización del panel disponible',
        'domain_changed'    => 'Sitio/dominio creado o eliminado',
        'user_created'      => 'Nuevo usuario creado',
    ];

    /**
     * Default state for each type (they are all enabled by default when
     * Telegram itself is enabled).
     */
    private const DEFAULTS = [
        'login'             => true,
        'login_new_device'  => true,
        'login_failed'      => true,
        'disk_threshold'    => true,
        'ram_threshold'     => true,
        'uptime_down'       => true,
        'backup_failed'     => true,
        'backup_completed'  => false,
        'update_available'  => true,
        'domain_changed'    => true,
        'user_created'      => true,
    ];

    public static function types(): array
    {
        return self::TYPES;
    }

    /**
     * Whether a given notice type is enabled by the admin.
     */
    public static function isEnabled(string $type): bool
    {
        if (! array_key_exists($type, self::TYPES)) {
            return true;
        }

        $default = self::DEFAULTS[$type] ?? true;

        return filter_var(
            Setting::get('notice_' . $type, $default ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Set whether a notice type is enabled.
     */
    public static function setEnabled(string $type, bool $enabled): void
    {
        if (! array_key_exists($type, self::TYPES)) {
            return;
        }

        Setting::set('notice_' . $type, $enabled ? '1' : '0');
    }
}

<?php

namespace App\Providers;

use App\Services\MonitoringService;
use App\Shell\ShellExecutor;
use App\Shell\SudoExecutor;
use App\Services\TerminalSessionManager;
use App\Listeners\HandleTerminalMessage;
use App\Listeners\KillTerminalOnDisconnect;
use App\Listeners\NotifySuspiciousLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageReceived;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind shell executors as singletons
        $this->app->singleton(ShellExecutor::class);
        $this->app->singleton(SudoExecutor::class);

        // Monitoring service (lightweight, /proc reads)
        $this->app->singleton(MonitoringService::class);
        $this->app->singleton(\App\Services\DomainService::class);
        $this->app->singleton(\App\Services\SslService::class);
        $this->app->singleton(\App\Services\PhpService::class);
        $this->app->singleton(\App\Services\DatabaseService::class);
        $this->app->singleton(\App\Services\FileService::class);
        $this->app->singleton(\App\Services\EmailService::class);
        $this->app->singleton(\App\Services\FtpService::class);
        $this->app->singleton(\App\Services\CronService::class);
        $this->app->singleton(\App\Services\BackupService::class);
        // Phase 2
        $this->app->singleton(\App\Services\DnsService::class);
        $this->app->singleton(\App\Services\DkimService::class);
        $this->app->singleton(\App\Services\AntispamService::class);
        // Phase 3
        $this->app->singleton(\App\Services\FirewallService::class);
        $this->app->singleton(\App\Services\Fail2banService::class);
        
        // Phase 5
        $this->app->singleton(\App\Services\TerminalService::class);
        $this->app->singleton(TerminalSessionManager::class);
        $this->app->singleton(\App\Services\GitService::class);
        $this->app->singleton(\App\Services\WordPressService::class);
        $this->app->singleton(\App\Services\ServerService::class);
        // Phase 10
        $this->app->singleton(\App\Services\GoAccessService::class);
        $this->app->singleton(\App\Services\GeoWafService::class);
    }

    public function boot(): void
    {
        // 1. Auto-curación de permisos y carpetas de storage y database en boot
        $frameworkPaths = [
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            database_path(),
        ];

        foreach ($frameworkPaths as $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            if (is_dir($path) && !is_writable($path)) {
                @chmod($path, 0777);
            }
        }

        $sqliteFile = database_path('database.sqlite');
        if (!file_exists($sqliteFile)) {
            @touch($sqliteFile);
            @chmod($sqliteFile, 0666);
        } elseif (!is_writable($sqliteFile)) {
            @chmod($sqliteFile, 0666);
        }

        // Enforce 2FA for admin users (Phase 0 stub — full impl in Phase 1)
        // \Illuminate\Support\Facades\Gate::define('admin', fn($user) => $user->isAdmin());

        // Cargar credenciales AWS globales desde Settings
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                config([
                     'filesystems.disks.s3.key' => \App\Models\Setting::getSecret('aws_access_key_id', env('AWS_ACCESS_KEY_ID')),
                     'filesystems.disks.s3.secret' => \App\Models\Setting::getSecret('aws_secret_access_key', env('AWS_SECRET_ACCESS_KEY')),
                    'filesystems.disks.s3.region' => \App\Models\Setting::get('aws_default_region', env('AWS_DEFAULT_REGION', 'us-east-1')),
                    'filesystems.disks.s3.bucket' => \App\Models\Setting::get('aws_bucket', env('AWS_BUCKET')),
                    'filesystems.disks.s3.endpoint' => \App\Models\Setting::get('aws_endpoint', env('AWS_ENDPOINT')),
                ]);
            }
        } catch (\Exception $e) {
            // Ignorar si la base de datos no está lista (ej. durante php artisan migrate)
        }

        // Interactive terminal sockets (Reverb long-lived process)
        Event::listen(MessageReceived::class, HandleTerminalMessage::class);
        Event::listen(ChannelRemoved::class, KillTerminalOnDisconnect::class);

        // Suspicious login detection (Telegram alert on new IP)
        Event::listen(Login::class, NotifySuspiciousLogin::class);

        // Load Telegram notification settings from the DB into config so that
        // config('larapanel.notifications.telegram.*') reflects the UI values.
        try {
            if (Schema::hasTable('settings')) {
                config([
                    'larapanel.notifications.telegram.enabled' => filter_var(
                        \App\Models\Setting::get(
                            'telegram_enabled',
                            config('larapanel.notifications.telegram.enabled')
                        ),
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'larapanel.notifications.telegram.bot_token' => \App\Models\Setting::getSecret(
                        'telegram_bot_token',
                        config('larapanel.notifications.telegram.bot_token')
                    ),
                    'larapanel.notifications.telegram.chat_id' => \App\Models\Setting::get(
                        'telegram_chat_id',
                        config('larapanel.notifications.telegram.chat_id')
                    ),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore if the database is not ready (e.g. during migrate).
        }
    }
}

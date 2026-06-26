<?php

namespace App\Providers;

use App\Services\MonitoringService;
use App\Shell\ShellExecutor;
use App\Shell\SudoExecutor;
use Illuminate\Support\ServiceProvider;

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
        $this->app->singleton(\App\Services\GitService::class);
        $this->app->singleton(\App\Services\WordPressService::class);
        $this->app->singleton(\App\Services\ServerService::class);
    }

    public function boot(): void
    {
        // Enforce 2FA for admin users (Phase 0 stub — full impl in Phase 1)
        // \Illuminate\Support\Facades\Gate::define('admin', fn($user) => $user->isAdmin());

        // Cargar credenciales AWS globales desde Settings
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                config([
                    'filesystems.disks.s3.key' => \App\Models\Setting::get('aws_access_key_id', env('AWS_ACCESS_KEY_ID')),
                    'filesystems.disks.s3.secret' => \App\Models\Setting::get('aws_secret_access_key', env('AWS_SECRET_ACCESS_KEY')),
                    'filesystems.disks.s3.region' => \App\Models\Setting::get('aws_default_region', env('AWS_DEFAULT_REGION', 'us-east-1')),
                    'filesystems.disks.s3.bucket' => \App\Models\Setting::get('aws_bucket', env('AWS_BUCKET')),
                    'filesystems.disks.s3.endpoint' => \App\Models\Setting::get('aws_endpoint', env('AWS_ENDPOINT')),
                ]);
            }
        } catch (\Exception $e) {
            // Ignorar si la base de datos no está lista (ej. durante php artisan migrate)
        }
    }
}

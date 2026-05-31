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
    }
}

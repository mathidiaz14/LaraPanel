<?php

use App\Livewire\Dashboard;
use App\Livewire\Domains\DomainIndex;
use App\Livewire\Domains\DomainCreate;
use App\Livewire\SSL\SslIndex;
use App\Livewire\SSL\SslIssue;
use App\Livewire\SSL\SslInstall;
use App\Livewire\PHP\PhpIndex;
use App\Livewire\Databases\DatabaseIndex;
use App\Livewire\Files\FileManager;
use App\Livewire\Email\EmailIndex;
use App\Livewire\Email\EmailAliases;
use App\Livewire\Email\EmailAutoresponders;
use App\Livewire\Email\DkimManager;
use App\Livewire\Email\EmailStats;
use App\Livewire\FTP\FtpIndex;
use App\Livewire\Cron\CronIndex;
use App\Livewire\Backups\BackupIndex;
use App\Livewire\DNS\DnsIndex;
use App\Livewire\DNS\DnsZoneEditor;
use App\Livewire\Antispam\AntispamIndex;
use App\Livewire\Firewall\FirewallIndex;
use App\Livewire\Fail2ban\Fail2banIndex;
use App\Livewire\Antivirus\AntivirusIndex;
use App\Livewire\Servers\ServersIndex;
use App\Livewire\Terminal\TerminalIndex;
use App\Livewire\Git\GitIndex;
use App\Livewire\Logs\LogIndex;
use App\Livewire\WordPress\WordPressIndex;
use App\Livewire\Docker\DockerIndex;
use App\Livewire\Admin\PlanIndex;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Admin\ApiTokens;
use App\Http\Controllers\GitWebhookController;
use App\Http\Controllers\WebmailAutoLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LaraPanel Web Routes
|--------------------------------------------------------------------------
*/

// ── Auth (Fortify handles POST routes) ──────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::get('/forgot-password', fn() => view('auth.forgot-password'))->name('password.request');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout')->middleware('auth');

// ── Authenticated Panel ──────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', Dashboard::class)->name('dashboard');

    // Domains
    Route::get('/domains',        DomainIndex::class)->name('domains.index');
    Route::get('/domains/create', DomainCreate::class)->name('domains.create');

    // SSL
    Route::get('/ssl',         SslIndex::class)->name('ssl.index');
    Route::get('/ssl/issue',   SslIssue::class)->name('ssl.issue');
    Route::get('/ssl/install', SslInstall::class)->name('ssl.install');

    // PHP
    Route::get('/php', PhpIndex::class)->name('php.index');

    // Email
    Route::get('/email',              EmailIndex::class)->name('email.index');
    Route::get('/email/aliases',      EmailAliases::class)->name('email.aliases');
    Route::get('/email/autoresponders', EmailAutoresponders::class)->name('email.autoresponders');
    Route::get('/email/dkim',         DkimManager::class)->name('email.dkim');
    Route::get('/email/stats',        EmailStats::class)->name('email.stats');
    Route::get('/email/{id}/backup',  [WebmailAutoLoginController::class, 'backup'])->name('email.backup');

    // Databases
    Route::get('/databases', DatabaseIndex::class)->name('databases.index');

    // File Manager
    Route::get('/files', FileManager::class)->name('files.index');

    // FTP
    Route::get('/ftp', FtpIndex::class)->name('ftp.index');

    // DNS
    Route::get('/dns',       DnsIndex::class)->name('dns.index');
    Route::get('/dns/{zone}',DnsZoneEditor::class)->name('dns.zone');

    // Antispam
    Route::get('/antispam', AntispamIndex::class)->name('antispam.index');

    // Firewall
    Route::get('/firewall',  FirewallIndex::class)->name('firewall.index');
    Route::get('/fail2ban',  Fail2banIndex::class)->name('fail2ban.index');

    // Antivirus
    Route::get('/antivirus', AntivirusIndex::class)->name('antivirus.index');

    // Antispam
    Route::get('/cron', CronIndex::class)->name('cron.index');

    // Backups
    Route::get('/backups', BackupIndex::class)->name('backups.index');

    // Git Deploy
    Route::get('/git', GitIndex::class)->name('git.index');

    // Docker
    Route::get('/docker', DockerIndex::class)->name('docker.index');

    // WordPress
    Route::get('/wordpress', WordPressIndex::class)->name('wordpress.index');

    // Terminal (admin only)
    Route::get('/terminal', TerminalIndex::class)->name('terminal.index');

    // Multi-Server Cluster Management
    Route::get('/servers', ServersIndex::class)->name('servers.index');

    // Logs
    Route::get('/logs', LogIndex::class)->name('logs.index');

    // Profile
    Route::get('/profile', fn() => view('coming-soon', ['module' => 'Mi Perfil']))->name('profile');

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
        Route::get('/users',   UserIndex::class)->name('users.index');
        Route::get('/plans',   PlanIndex::class)->name('plans.index');
        Route::get('/api-tokens', ApiTokens::class)->name('api-tokens');
        Route::get('/settings',   \App\Livewire\Admin\Settings::class)->name('settings');
    });
});

// ── Webhooks (Public) ─────────────────────────────────────────────
Route::post('/api/webhooks/git/{uuid}', [GitWebhookController::class, 'handle'])->name('webhooks.git');

// ── Webmail Auto-Login (Signed, Public) ───────────────────────────
Route::get('/webmail/autologin/{token}', [WebmailAutoLoginController::class, 'autologin'])->name('webmail.autologin');


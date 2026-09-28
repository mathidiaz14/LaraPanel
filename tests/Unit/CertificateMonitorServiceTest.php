<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\User;
use App\Services\CertificateMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateMonitorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activeDomain(array $overrides = []): Domain
    {
        $user = User::factory()->create(['role' => 'client']);

        return Domain::create(array_merge([
            'user_id'       => $user->id,
            'name'          => 'example.com',
            'type'          => 'main',
            'document_root' => '/var/www/example.com/public_html',
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'status'        => 'active',
            'is_active'     => true,
        ], $overrides));
    }

    public function test_groups_certificates_by_health(): void
    {
        $this->activeDomain(['name' => 'expired.com',     'ssl_expires_at' => now()->subDays(2)]);
        $this->activeDomain(['name' => 'soon.com',        'ssl_expires_at' => now()->addDays(5)]);
        $this->activeDomain(['name' => 'later.com',       'ssl_expires_at' => now()->addMonths(2)]);
        $this->activeDomain(['name' => 'no-ssl.com',      'ssl_enabled'    => false]);
        $this->activeDomain(['name' => 'inactive.com',    'is_active'      => false, 'ssl_expires_at' => now()->addDays(1)]);

        $scan = app(CertificateMonitorService::class)->scanExpiring(14);

        $this->assertCount(1, $scan['expired']);
        $this->assertTrue($scan['expired']->first()->name === 'expired.com');

        $this->assertCount(1, $scan['expiring_soon']);
        $this->assertTrue($scan['expiring_soon']->first()->name === 'soon.com');

        $this->assertCount(1, $scan['expiring_later']);
        $this->assertCount(1, $scan['no_ssl']);
        $this->assertTrue($scan['no_ssl']->first()->name === 'no-ssl.com');
        // Inactive domains are never scanned.
        $this->assertTrue($scan['expiring_soon']->doesntContain(fn (Domain $d) => $d->name === 'inactive.com'));
    }

    public function test_avoid_duplicate_alerts_within_same_window(): void
    {
        $domain = $this->activeDomain(['ssl_expires_at' => now()->addDays(5)]);

        $service = app(CertificateMonitorService::class);

        $this->assertFalse($service->wasAlertedForWindow($domain, $domain->ssl_expires_at));

        $domain->update(['last_ssl_alerted_at' => now()]);
        $domain = $domain->fresh();

        // Same expiring certificate: already flagged within this window.
        $this->assertTrue($service->wasAlertedForWindow($domain, $domain->ssl_expires_at));
    }

    public function test_send_alerts_is_noop_outside_production(): void
    {
        $this->app['env'] = 'testing';
        $this->activeDomain(['ssl_expires_at' => now()->addDays(3)]);

        $this->assertSame(0, app(CertificateMonitorService::class)->sendAlerts(14));
    }
}
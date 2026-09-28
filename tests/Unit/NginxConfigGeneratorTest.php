<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\DomainPerformanceSetting;
use App\Models\User;
use App\Services\NginxConfigGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NginxConfigGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function domain(array $overrides = []): Domain
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
        ], $overrides));
    }

    public function test_generates_base_nginx_config(): void
    {
        $domain = $this->domain();
        $config = app(NginxConfigGenerator::class)->generateNginxConfig($domain);

        $this->assertStringContainsString('server_name example.com www.example.com;', $config);
        $this->assertStringContainsString('root /var/www/example.com/public_html;', $config);
        $this->assertStringContainsString('fastcgi_pass unix:/run/php/php8.3-fpm.sock;', $config);
        $this->assertStringContainsString('location ^~ /.well-known/acme-challenge/', $config);
        $this->assertStringContainsString('add_header X-Frame-Options "SAMEORIGIN" always;', $config);
    }

    public function test_renders_custom_error_pages_and_ignores_invalid_codes(): void
    {
        $domain = $this->domain([
            'error_pages' => [
                '404' => '/404.html',
                '500' => '/error/500.html',
                '999' => '/invalid.html',
                ''    => '/empty.html',
            ],
        ]);

        $config = app(NginxConfigGenerator::class)->generateNginxConfig($domain);

        $this->assertStringContainsString('error_page 404 /404.html;', $config);
        $this->assertStringContainsString('error_page 500 /error/500.html;', $config);
        $this->assertStringNotContainsString('999', $config);
        $this->assertStringNotContainsString('empty.html', $config);
    }

    public function test_renders_staging_upstream_when_configured(): void
    {
        $domain = $this->domain([
            'type'   => 'proxy',
            'config' => [
                'staging_upstreams' => [
                    ['address' => '10.0.0.11:8080'],
                    ['address' => '10.0.0.12:8080'],
                ],
            ],
        ]);

        $config = app(NginxConfigGenerator::class)->generateNginxConfig($domain);

        $this->assertStringContainsString('upstream staging_example_com {', $config);
        $this->assertStringContainsString('server 10.0.0.11:8080;', $config);
        $this->assertStringContainsString('server 10.0.0.12:8080;', $config);
        $this->assertStringContainsString('proxy_pass http://staging_example_com;', $config);
    }

    public function test_ssl_config_contains_hsts_and_redirect_when_enabled(): void
    {
        $domain = $this->domain();
        $this->assertNull($domain->performanceSetting);

        DomainPerformanceSetting::create([
            'domain_id'             => $domain->id,
            'hsts_enabled'          => true,
            'hsts_max_age'          => 31536000,
            'hsts_include_subdomains' => true,
        ]);

        $domain = $domain->fresh();

        $config = app(NginxConfigGenerator::class)->generateNginxSslConfig(
            $domain,
            '/etc/letsencrypt/live/example.com/fullchain.pem',
            '/etc/letsencrypt/live/example.com/privkey.pem',
        );

        $this->assertStringContainsString('ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;', $config);
        $this->assertStringContainsString('return 301 https://$host$request_uri;', $config);
        $this->assertStringContainsString('add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;', $config);
    }

    public function test_generates_apache_config(): void
    {
        $domain = $this->domain(['webserver' => 'apache']);
        $config = app(NginxConfigGenerator::class)->generateApacheConfig($domain);

        $this->assertStringContainsString('<VirtualHost *:80>', $config);
        $this->assertStringContainsString('ServerName example.com', $config);
        $this->assertStringContainsString('proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost', $config);
    }

    public function test_proxy_port_still_supported(): void
    {
        $domain = $this->domain([
            'type'   => 'proxy',
            'config' => ['proxy_port' => 3000],
        ]);

        $config = app(NginxConfigGenerator::class)->generateNginxConfig($domain);

        $this->assertStringContainsString('proxy_pass http://127.0.0.1:3000;', $config);
    }

    public function test_roundcube_webroot_gets_hardening_and_no_webmail_redirect(): void
    {
        $domain = $this->domain([
            'name'          => 'webmail.example.com',
            'document_root' => '/opt/roundcube',
        ]);

        $config = app(NginxConfigGenerator::class)->generateNginxConfig($domain);

        $this->assertStringContainsString('location ^~ /installer {', $config);
        $this->assertStringContainsString('location ^~ /SQL {', $config);
        $this->assertStringNotContainsString('location ^~ /webmail {', $config);
    }
}
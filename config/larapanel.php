<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LaraPanel Core Configuration
    |--------------------------------------------------------------------------
    */

    'version' => env('LARAPANEL_VERSION', '0.1.0-alpha'),

    'name' => 'LaraPanel',

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    */

    'server' => [
        'os' => 'ubuntu',  // ubuntu | debian | almalinux | rocky
        'webserver' => env('LARAPANEL_WEBSERVER', 'nginx'),  // nginx | apache | both
        'php_versions' => ['8.1', '8.2', '8.3'],
        'default_php' => env('LARAPANEL_DEFAULT_PHP', '8.3'),
        'sudo_user' => env('LARAPANEL_SUDO_USER', 'www-data'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules (enable/disable features)
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'domains'    => true,
        'email'      => true,
        'databases'  => true,
        'filemanager'=> true,
        'ssl'        => true,
        'dns'        => true,
        'firewall'   => true,
        'ftp'        => true,
        'cron'       => true,
        'backups'    => true,
        'terminal'   => true,
        'monitoring' => true,
        'logs'       => true,
        'phpmanager' => true,
        'docker'     => true,
        'antivirus'  => true,
        'resellers'  => false,  // Phase 4
        'multiserver'=> true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        '2fa_required_for_admin' => true,
        'session_timeout_minutes' => 60,
        'max_login_attempts' => 5,
        'audit_log_retention_days' => 365,
        'allowed_sudo_commands' => [
            // Explicitly whitelisted commands for the shell executor
            'systemctl',
            'nginx',
            'php-fpm',
            'mysql',
            'certbot',
            'ufw',
            'fail2ban-client',
            'useradd',
            'userdel',
            'chown',
            'chmod',
            'crontab',
            'docker',
            'clamscan',
            'freshclam',
            'clamdscan',
            'rm',
            'mv',
            'update.sh',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'webroots'     => '/var/www',
        'nginx_sites'  => '/etc/nginx/sites-available',
        'nginx_enabled'=> '/etc/nginx/sites-enabled',
        'apache_sites' => '/etc/apache2/sites-available',
        'ssl_certs'    => '/etc/ssl/larapanel',
        'backups'      => '/var/larapanel/backups',
        'logs'         => '/var/log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'metrics_interval_seconds' => 5,
        'history_retention_hours'  => 24,
        'alerts' => [
            'cpu_threshold'    => 90,
            'ram_threshold'    => 90,
            'disk_threshold'   => 85,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    */

    'backups' => [
        'default_driver' => 'local',  // local | s3 | sftp
        'retention_days' => 30,
        'compress' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | PowerDNS Configuration (Phase 2)
    |--------------------------------------------------------------------------
    */

    'powerdns' => [
        'api_url'  => env('PDNS_API_URL', 'http://127.0.0.1:8053/api/v1'),
        'api_key'  => env('PDNS_API_KEY', 'larapanel_pdns_secret'),
        'server'   => env('PDNS_SERVER', 'localhost'),
    ],

    'dns' => [
        'primary_ns'   => env('DNS_PRIMARY_NS', 'ns1.example.com'),
        'secondary_ns' => env('DNS_SECONDARY_NS', 'ns2.example.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rspamd Antispam (Phase 2)
    |--------------------------------------------------------------------------
    */

    'rspamd' => [
        'api_url'  => env('RSPAMD_API_URL', 'http://127.0.0.1:11334'),
        'password' => env('RSPAMD_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Server Configuration (Phase 2)
    |--------------------------------------------------------------------------
    */

    'mail' => [
        'postfix_config_path' => env('POSTFIX_CONFIG_PATH', '/etc/postfix'),
        'dovecot_config_path' => env('DOVECOT_CONFIG_PATH', '/etc/dovecot/conf.d'),
        'mailboxes_root'      => env('MAIL_MAILBOXES_ROOT', '/var/mail/vhosts'),
        'dkim_keys_path'      => env('DKIM_KEYS_PATH', '/etc/rspamd/dkim'),
    ],

    'server' => [
        'os'         => 'ubuntu',
        'webserver'  => env('LARAPANEL_WEBSERVER', 'nginx'),
        'php_versions' => ['8.1', '8.2', '8.3'],
        'default_php'  => env('LARAPANEL_DEFAULT_PHP', '8.3'),
        'sudo_user'    => env('LARAPANEL_SUDO_USER', 'www-data'),
        'public_ip'    => env('SERVER_PUBLIC_IP', '127.0.0.1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Docker Configuration
    |--------------------------------------------------------------------------
    */
    'docker' => [
        'socket'            => '/var/run/docker.sock',
        'max_log_lines'     => 500,
        'compose_base_path' => '/var/larapanel/compose',
    ],

    /*
    |--------------------------------------------------------------------------
    | Antivirus (ClamAV) Configuration
    |--------------------------------------------------------------------------
    */
    'antivirus' => [
        'quarantine_path'    => env('AV_QUARANTINE_PATH', '/var/larapanel/quarantine'),
        'max_scan_timeout'   => 300, // seconds (5 min)
        'allowed_scan_paths' => [
            '/home',
            '/var/www',
            '/tmp',
            '/var/larapanel',
            '/uploads',
        ],
    ],

];


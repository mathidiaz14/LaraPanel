<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────────
        $adminRole    = Role::firstOrCreate(['name' => 'admin']);
        $resellerRole = Role::firstOrCreate(['name' => 'reseller']);
        $clientRole   = Role::firstOrCreate(['name' => 'client']);

        // ── Default Plans ────────────────────────────────
        $basicPlan = Plan::firstOrCreate(['slug' => 'basic'], [
            'name'               => 'Basic',
            'price'              => 0,
            'max_domains'        => 1,
            'max_subdomains'     => 5,
            'max_email_accounts' => 5,
            'max_databases'      => 2,
            'max_ftp_accounts'   => 2,
            'disk_quota_bytes'   => 1073741824,    // 1 GB
            'bandwidth_bytes'    => 10737418240,   // 10 GB
            'max_cron_jobs'      => 5,
            'ssl_enabled'        => true,
            'backups_enabled'    => false,
            'terminal_enabled'   => false,
        ]);

        Plan::firstOrCreate(['slug' => 'pro'], [
            'name'               => 'Pro',
            'price'              => 9.99,
            'max_domains'        => 10,
            'max_subdomains'     => 50,
            'max_email_accounts' => 25,
            'max_databases'      => 10,
            'max_ftp_accounts'   => 10,
            'disk_quota_bytes'   => 10737418240,   // 10 GB
            'bandwidth_bytes'    => 107374182400,  // 100 GB
            'max_cron_jobs'      => 20,
            'ssl_enabled'        => true,
            'backups_enabled'    => true,
            'terminal_enabled'   => false,
        ]);

        Plan::firstOrCreate(['slug' => 'enterprise'], [
            'name'               => 'Enterprise',
            'price'              => 49.99,
            'max_domains'        => 999,
            'max_subdomains'     => 999,
            'max_email_accounts' => 999,
            'max_databases'      => 999,
            'max_ftp_accounts'   => 999,
            'disk_quota_bytes'   => 107374182400,  // 100 GB
            'bandwidth_bytes'    => 1099511627776,  // 1 TB
            'max_cron_jobs'      => 100,
            'ssl_enabled'        => true,
            'backups_enabled'    => true,
            'terminal_enabled'   => true,
        ]);

        // ── Admin User ───────────────────────────────────
        $admin = User::firstOrCreate(['email' => 'admin@larapanel.local'], [
            'name'     => 'Administrator',
            'password' => Hash::make('LaraPanel2024!'),
            'role'     => 'admin',
            'is_active'=> true,
            'timezone' => 'America/Argentina/Buenos_Aires',
            'language' => 'es',
        ]);

        $admin->assignRole($adminRole);

        $this->command->info('✅ LaraPanel seeded successfully!');
        $this->command->info('   Login: admin@larapanel.local');
        $this->command->info('   Password: LaraPanel2024!');
    }
}

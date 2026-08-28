<?php

namespace Tests\Feature;

use App\Models\DnsZone;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_another_users_dns_zone()
    {
        $owner = User::factory()->create(['role' => 'client']);
        $intruder = User::factory()->create(['role' => 'client']);

        $domain = Domain::create([
            'user_id'    => $owner->id,
            'name'       => 'owner-domain.com',
            'type'       => 'main',
            'status'     => 'active',
            'is_active'  => true,
        ]);

        $zone = DnsZone::create([
            'user_id'    => $owner->id,
            'domain_id'  => $domain->id,
            'name'       => 'owner-domain.com',
            'type'       => 'master',
            'is_active'  => true,
        ]);

        // The owner can view their own zone.
        $this->actingAs($owner)
            ->get("/dns/{$zone->id}")
            ->assertStatus(200);

        // Another client must be denied (authorization enforced in DnsZoneEditor).
        $this->actingAs($intruder)
            ->get("/dns/{$zone->id}")
            ->assertForbidden();
    }
}

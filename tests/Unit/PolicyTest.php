<?php

namespace Tests\Unit;

use App\Models\DatabaseInstance;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private int $domainSeq = 0;

    private function domainFor(User $user, string $name = 'example.com'): Domain
    {
        $this->domainSeq++;

        return Domain::create([
            'user_id'       => $user->id,
            'name'          => ($this->domainSeq === 1 ? $name : $this->domainSeq . '-' . $name),
            'type'          => 'main',
            'document_root' => '/var/www/' . ($this->domainSeq === 1 ? $name : $this->domainSeq . '-' . $name) . '/public_html',
            'php_version'   => '8.3',
            'webserver'     => 'nginx',
            'status'        => 'active',
        ]);
    }

    private function databaseFor(User $user, ?Domain $domain = null): DatabaseInstance
    {
        return DatabaseInstance::create([
            'user_id'       => $user->id,
            'domain_id'     => $domain?->id,
            'db_name'       => 'test_' . $user->id . '_one',
            'display_name'  => 'one',
            'db_user'       => 'user_' . $user->id,
            'engine'        => 'mysql',
            'is_active'     => true,
        ]);
    }

    public function test_admin_can_manage_any_domain(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $client = $this->user(['role' => 'client']);
        $domain = $this->domainFor($client);

        $this->assertTrue($admin->can('view', $domain));
        $this->assertTrue($admin->can('delete', $domain));
        $this->assertTrue($admin->can('create', Domain::class));
    }

    public function test_client_can_only_manage_own_domain(): void
    {
        $owner = $this->user(['role' => 'client']);
        $other = $this->user(['role' => 'client']);
        $domain = $this->domainFor($owner);

        $this->assertTrue($owner->can('view', $domain));
        $this->assertTrue($owner->can('update', $domain));
        $this->assertFalse($other->can('view', $domain));
        $this->assertFalse($other->can('delete', $domain));
    }

    public function test_reseller_can_manage_clients_but_not_other_reseller_clients(): void
    {
        $reseller   = $this->user(['role' => 'reseller']);
        $theirClient = $this->user(['role' => 'client', 'parent_id' => $reseller->id]);
        $otherReseller = $this->user(['role' => 'reseller']);
        $strangerClient = $this->user(['role' => 'client', 'parent_id' => $otherReseller->id]);

        $theirs = $this->domainFor($theirClient);
        $stranger = $this->domainFor($strangerClient);

        $this->assertTrue($reseller->can('view', $theirs));
        $this->assertTrue($reseller->can('update', $theirs));
        $this->assertFalse($reseller->can('view', $stranger));
    }

    public function test_database_policy_enforces_ownership(): void
    {
        $owner = $this->user(['role' => 'client']);
        $other = $this->user(['role' => 'client']);
        $db = $this->databaseFor($owner);

        $this->assertTrue($owner->can('view', $db));
        $this->assertTrue($owner->can('update', $db));
        $this->assertFalse($other->can('view', $db));
        $this->assertFalse($other->can('delete', $db));
    }

    public function test_user_policy_block_self_suspension_and_reseller_scope(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $reseller = $this->user(['role' => 'reseller']);
        $theirClient = $this->user(['role' => 'client', 'parent_id' => $reseller->id]);
        $stranger = $this->user(['role' => 'client']);

        // Admins can suspend anyone except themselves.
        $this->assertFalse($admin->can('suspend', $admin));
        $this->assertTrue($admin->can('suspend', $stranger));

        // Resellers can only manage their own clients.
        $this->assertTrue($reseller->can('update', $theirClient));
        $this->assertFalse($reseller->can('update', $stranger));

        // Resellers cannot impersonate users that are not their clients.
        $this->assertTrue($reseller->can('impersonate', $theirClient));
        $this->assertFalse($reseller->can('impersonate', $stranger));
        $this->assertTrue($admin->can('impersonate', $stranger));
    }
}
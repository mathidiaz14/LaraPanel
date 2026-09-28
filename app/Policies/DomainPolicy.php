<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DomainPolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Domain $domain): bool
    {
        return $this->manages($user, $domain);
    }

    public function create(User $user): bool
    {
        return $user->canAddDomain();
    }

    public function update(User $user, Domain $domain): bool
    {
        return $this->manages($user, $domain);
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $this->manages($user, $domain);
    }

    public function configurePerformance(User $user, Domain $domain): bool
    {
        return $this->manages($user, $domain);
    }

    public function configureErrorPages(User $user, Domain $domain): bool
    {
        return $this->manages($user, $domain);
    }

    public function manageStaging(User $user, Domain $domain): bool
    {
        return $user->isReseller() && $this->manages($user, $domain);
    }

    public function viewAudit(User $user, Domain $domain): bool
    {
        return $this->manages($user, $domain);
    }

    /**
     * Whether the user owns the domain (client) or is the reseller who
     * administers the owner.
     */
    protected function manages(User $user, Domain $domain): bool
    {
        if ($domain->user_id === $user->id) {
            return true;
        }

        return $user->isReseller() && $domain->user_id === $user->id
            || $user->clients()->whereKey($domain->user_id)->exists();
    }

    /**
     * Whether the domain belongs to the given user account (generic owner check).
     */
    public static function ownedBy(User $user, Domain $domain): bool
    {
        return $domain->user_id === $user->id;
    }
}
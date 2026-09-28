<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $authUser): ?bool
    {
        // No blanket bypass: admins are covered by managesAccount() so that
        // individual methods can still enforce self-operation guards.
        return null;
    }

    public function viewAny(User $authUser): bool
    {
        return $authUser->isAdmin() || $authUser->isReseller();
    }

    public function view(User $authUser, User $user): bool
    {
        return $this->managesAccount($authUser, $user);
    }

    public function create(User $authUser): bool
    {
        return $authUser->isAdmin() || $authUser->isReseller();
    }

    public function update(User $authUser, User $user): bool
    {
        return $this->managesAccount($authUser, $user);
    }

    public function suspend(User $authUser, User $user): bool
    {
        return $this->managesAccount($authUser, $user) && $authUser->id !== $user->id;
    }

    public function unsuspend(User $authUser, User $user): bool
    {
        return $this->managesAccount($authUser, $user) && $authUser->id !== $user->id;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $this->managesAccount($authUser, $user) && $authUser->id !== $user->id;
    }

    public function impersonate(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return false;
        }

        return $authUser->isAdmin()
            || ($authUser->isReseller() && $this->managesAccount($authUser, $user));
    }

    /**
     * Admins manage everyone; resellers manage only their own clients.
     */
    protected function managesAccount(User $authUser, User $account): bool
    {
        if ($authUser->isAdmin()) {
            return true;
        }

        if ($authUser->isClient()) {
            return $authUser->id === $account->id;
        }

        // Reseller: only clients directly (or transitively) below them.
        if ($account->id === $authUser->id) {
            return true;
        }

        if ($account->parent_id === $authUser->id) {
            return true;
        }

        return $account->parent_id !== null
            && $authUser->clients()->whereKey($account->parent_id)->exists()
            || $authUser->clients()->whereKey($account->id)->exists();
    }
}
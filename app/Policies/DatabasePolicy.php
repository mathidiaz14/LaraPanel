<?php

namespace App\Policies;

use App\Models\DatabaseInstance;
use App\Models\User;

class DatabasePolicy
{
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

    public function view(User $user, DatabaseInstance $database): bool
    {
        return $this->manages($user, $database);
    }

    public function create(User $user): bool
    {
        return $user->canAddDatabase();
    }

    public function update(User $user, DatabaseInstance $database): bool
    {
        return $this->manages($user, $database);
    }

    public function delete(User $user, DatabaseInstance $database): bool
    {
        return $this->manages($user, $database);
    }

    public function manageQueries(User $user, DatabaseInstance $database): bool
    {
        return $this->manages($user, $database);
    }

    protected function manages(User $user, DatabaseInstance $database): bool
    {
        if ($database->user_id === $user->id) {
            return true;
        }

        return $user->clients()->whereKey($database->user_id)->exists();
    }
}
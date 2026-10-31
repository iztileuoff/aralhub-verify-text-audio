<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\User;

class UserPolicy
{
    /**
     * The super admin manages everyone; an admin only the users assigned to
     * them (admin_id) — the same users UserController::index lists for them.
     * The routes already let in nobody but admins.
     */
    public function manage(User $actor, User $user): bool
    {
        return match ($actor->role) {
            RoleEnum::SUPER_ADMIN => true,
            RoleEnum::ADMIN => $user->admin_id === $actor->id,
            default => false,
        };
    }

    public function view(User $actor, User $user): bool
    {
        return $this->manage($actor, $user);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->manage($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->manage($actor, $user);
    }
}

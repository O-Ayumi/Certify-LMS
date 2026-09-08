<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function publish(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function archive(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function unarchive(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }
}

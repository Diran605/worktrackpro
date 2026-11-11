<?php

namespace App\Policies;

use App\Models\DailyPlan;
use App\Models\User;

class DailyPlanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Everyone can view lists; we filter the list at the query level.
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DailyPlan $dailyPlan): bool
    {
        // Users can see their own plans
        if ($user->id === $dailyPlan->user_id) {
            return true;
        }

        // Super admins can see all plans
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Admins can see plans within their defined department hierarchy
        if ($user->hasRole('admin') && $user->department_id === $dailyPlan->user->department_id) {
             return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('assign_plans') || $user->hasPermissionTo('manage_own_work');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DailyPlan $dailyPlan): bool
    {
        // Only the owner can UPDATE their daily plan
        return $user->id === $dailyPlan->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DailyPlan $dailyPlan): bool
    {
        // Only the owner can DELETE their daily plan
        return $user->id === $dailyPlan->user_id;
    }
}

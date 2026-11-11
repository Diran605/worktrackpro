<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\User;

class PlanService
{
    /**
     * Create a new Daily Plan.
     */
    public function createPlan(User $user, array $data): DailyPlan
    {
        $data['user_id'] = $user->id;
        // Automatic inheritance from auth user to avoid tampering
        $data['organisation_id'] = $user->organisation_id;

        return DailyPlan::create($data);
    }

    /**
     * Update an existing Daily Plan.
     */
    public function updatePlan(DailyPlan $plan, array $data): DailyPlan
    {
        $plan->update($data);
        return $plan;
    }
}

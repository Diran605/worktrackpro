<?php

namespace App\Policies;

use App\Models\System;
use App\Models\User;
use App\Support\Permissions;

class SystemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_KB) || $user->can(Permissions::crud('systems', 'view'));
    }

    public function view(User $user, System $system): bool
    {
        return $this->sameOrganisation($user, $system) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::crud('systems', 'create'));
    }

    /**
     * Registry managers can edit any system in their organisation; owners can edit their own.
     */
    public function update(User $user, System $system): bool
    {
        if (!$this->sameOrganisation($user, $system)) {
            return false;
        }

        return $user->can(Permissions::crud('systems', 'update')) || $system->isOwnedBy($user);
    }

    public function delete(User $user, System $system): bool
    {
        return $this->sameOrganisation($user, $system) && $user->can(Permissions::crud('systems', 'delete'));
    }

    /**
     * Writing or uploading manual content (always goes through owner approval).
     */
    public function contribute(User $user, System $system): bool
    {
        return $this->sameOrganisation($user, $system) && $user->can(Permissions::CONTRIBUTE_KB);
    }

    public function approve(User $user, System $system): bool
    {
        return $system->canBeApprovedBy($user);
    }

    private function sameOrganisation(User $user, System $system): bool
    {
        return $user->hasRole('super_admin') || $user->organisation_id === $system->organisation_id;
    }
}

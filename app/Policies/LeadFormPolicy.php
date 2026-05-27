<?php

namespace App\Policies;

use App\Models\LeadForm;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class LeadFormPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, LeadForm $leadForm): bool
    {
        return $this->sameTenant($user, $leadForm) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, LeadForm $leadForm): bool
    {
        return $this->sameTenant($user, $leadForm) && $this->canManage($user);
    }

    public function delete(User $user, LeadForm $leadForm): bool
    {
        return $this->sameTenant($user, $leadForm) && $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $this->hasAnyTenantRole($user, [
            Tenant::ROLE_OWNER,
            Tenant::ROLE_MANAGER,
        ]);
    }
}

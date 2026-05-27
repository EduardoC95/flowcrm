<?php

namespace App\Policies;

use App\Models\AutomationRule;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class AutomationRulePolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AutomationRule $automationRule): bool
    {
        return $this->sameTenant($user, $automationRule) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AutomationRule $automationRule): bool
    {
        return $this->sameTenant($user, $automationRule) && $this->canManage($user);
    }

    public function delete(User $user, AutomationRule $automationRule): bool
    {
        return $this->sameTenant($user, $automationRule) && $this->canManage($user);
    }

    public function pause(User $user, AutomationRule $automationRule): bool
    {
        return $this->update($user, $automationRule);
    }

    public function resume(User $user, AutomationRule $automationRule): bool
    {
        return $this->update($user, $automationRule);
    }

    private function canManage(User $user): bool
    {
        return $this->hasAnyTenantRole($user, [
            Tenant::ROLE_OWNER,
            Tenant::ROLE_MANAGER,
        ]);
    }
}

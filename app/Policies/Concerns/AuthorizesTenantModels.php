<?php

namespace App\Policies\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesTenantModels
{
    protected function sameTenant(User $user, Model $model): bool
    {
        return (int) $model->tenant_id === (int) $user->current_tenant_id;
    }

    protected function hasAnyTenantRole(User $user, array $roles): bool
    {
        $role = $user->roleForTenant();

        return $role !== null && in_array($role, $roles, true);
    }

    protected function canView(User $user): bool
    {
        return $this->hasAnyTenantRole($user, Tenant::ROLES);
    }

    protected function canWrite(User $user): bool
    {
        return $this->hasAnyTenantRole($user, [
            Tenant::ROLE_OWNER,
            Tenant::ROLE_MANAGER,
            Tenant::ROLE_SALES,
        ]);
    }

    protected function canDelete(User $user): bool
    {
        return $this->hasAnyTenantRole($user, [
            Tenant::ROLE_OWNER,
            Tenant::ROLE_MANAGER,
        ]);
    }
}

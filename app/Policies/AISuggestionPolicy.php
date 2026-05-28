<?php

namespace App\Policies;

use App\Models\AISuggestion;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class AISuggestionPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AISuggestion $suggestion): bool
    {
        return $this->sameTenant($user, $suggestion)
            && $this->canView($user)
            && ($this->canManageAll($user) || (int) $suggestion->user_id === (int) $user->id);
    }

    public function accept(User $user, AISuggestion $suggestion): bool
    {
        return $this->canAct($user, $suggestion);
    }

    public function postpone(User $user, AISuggestion $suggestion): bool
    {
        return $this->canAct($user, $suggestion);
    }

    public function archive(User $user, AISuggestion $suggestion): bool
    {
        return $this->canAct($user, $suggestion);
    }

    public function ignore(User $user, AISuggestion $suggestion): bool
    {
        return $this->canAct($user, $suggestion);
    }

    public function convertToActivity(User $user, AISuggestion $suggestion): bool
    {
        return $this->canAct($user, $suggestion);
    }

    private function canAct(User $user, AISuggestion $suggestion): bool
    {
        return $this->sameTenant($user, $suggestion)
            && $this->canWrite($user)
            && ($this->canManageAll($user) || (int) $suggestion->user_id === (int) $user->id);
    }

    private function canManageAll(User $user): bool
    {
        return in_array($user->roleForTenant(), [Tenant::ROLE_OWNER, Tenant::ROLE_MANAGER], true);
    }
}

<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class DealPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Deal $deal): bool
    {
        return $this->sameTenant($user, $deal) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->sameTenant($user, $deal) && $this->canWrite($user);
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->sameTenant($user, $deal) && $this->canWrite($user);
    }
}

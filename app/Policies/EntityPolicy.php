<?php

namespace App\Policies;

use App\Models\Entity;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class EntityPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Entity $entity): bool
    {
        return $this->sameTenant($user, $entity) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Entity $entity): bool
    {
        return $this->sameTenant($user, $entity) && $this->canWrite($user);
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $this->sameTenant($user, $entity) && $this->canDelete($user);
    }
}

<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class PersonPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Person $person): bool
    {
        return $this->sameTenant($user, $person) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Person $person): bool
    {
        return $this->sameTenant($user, $person) && $this->canWrite($user);
    }

    public function delete(User $user, Person $person): bool
    {
        return $this->sameTenant($user, $person) && $this->canDelete($user);
    }
}

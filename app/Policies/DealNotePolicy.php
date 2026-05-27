<?php

namespace App\Policies;

use App\Models\DealNote;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class DealNotePolicy
{
    use AuthorizesTenantModels;

    public function view(User $user, DealNote $dealNote): bool
    {
        return $this->sameTenant($user, $dealNote) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, DealNote $dealNote): bool
    {
        return $this->sameTenant($user, $dealNote) && $this->canWrite($user);
    }

    public function delete(User $user, DealNote $dealNote): bool
    {
        return $this->sameTenant($user, $dealNote) && $this->canWrite($user);
    }
}

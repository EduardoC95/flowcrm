<?php

namespace App\Policies;

use App\Models\AIChatConversation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class AIChatPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AIChatConversation $conversation): bool
    {
        return $this->sameTenant($user, $conversation) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canView($user);
    }

    public function delete(User $user, AIChatConversation $conversation): bool
    {
        return $this->sameTenant($user, $conversation) && $this->canWrite($user);
    }
}

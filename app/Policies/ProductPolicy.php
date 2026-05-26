<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class ProductPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $this->canWrite($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $this->canWrite($user);
    }
}

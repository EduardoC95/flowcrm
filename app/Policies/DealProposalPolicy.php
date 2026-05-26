<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class DealProposalPolicy
{
    use AuthorizesTenantModels;

    public function view(User $user, DealProposal $proposal): bool
    {
        return $this->sameTenant($user, $proposal) && $this->canView($user);
    }

    public function create(User $user, Deal $deal): bool
    {
        return $this->sameTenant($user, $deal) && $this->canWrite($user);
    }

    public function upload(User $user, Deal $deal): bool
    {
        return $this->create($user, $deal);
    }

    public function send(User $user, DealProposal $proposal): bool
    {
        return $this->sameTenant($user, $proposal) && $this->canWrite($user);
    }

    public function delete(User $user, DealProposal $proposal): bool
    {
        return $this->sameTenant($user, $proposal) && $this->canWrite($user);
    }

    public function download(User $user, DealProposal $proposal): bool
    {
        return $this->sameTenant($user, $proposal) && $this->canView($user);
    }
}

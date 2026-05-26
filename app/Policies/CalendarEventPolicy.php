<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantModels;

class CalendarEventPolicy
{
    use AuthorizesTenantModels;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameTenant($user, $calendarEvent) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameTenant($user, $calendarEvent) && $this->canWrite($user);
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameTenant($user, $calendarEvent) && $this->canWrite($user);
    }

    public function complete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameTenant($user, $calendarEvent) && $this->canWrite($user);
    }

    public function cancel(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->sameTenant($user, $calendarEvent) && $this->canWrite($user);
    }
}

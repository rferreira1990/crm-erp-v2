<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarEventPolicy extends BaseCompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.calendar.view');
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->canAccessCompanyResource($user, $calendarEvent)
            && $user->can('company.calendar.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.calendar.create');
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->canAccessCompanyResource($user, $calendarEvent)
            && $user->can('company.calendar.update');
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->canAccessCompanyResource($user, $calendarEvent)
            && $user->can('company.calendar.delete');
    }
}


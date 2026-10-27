<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('MEMBER')) {
            return $user->member !== null;
        }

        return $user->hasPermissionTo('reservations.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->hasRole('MEMBER')) {
            return $user->member?->id === $reservation->member_id;
        }

        return $user->hasPermissionTo('reservations.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reservations.create') || $user->member !== null;
    }

    /**
     * Determine whether the user can cancel the reservation.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($user->hasRole('MEMBER')) {
            return $user->member?->id === $reservation->member_id;
        }

        return $user->hasPermissionTo('reservations.cancel');
    }

    /**
     * Determine whether the user can restore the reservation.
     */
    public function restore(User $user, Reservation $reservation): bool
    {
        if ($user->hasRole('MEMBER')) {
            return $user->member?->id === $reservation->member_id;
        }

        return $user->hasPermissionTo('reservations.cancel');
    }

    /**
     * Determine whether the user can assign copies or manage.
     */
    public function manage(User $user): bool
    {
        return ! $user->hasRole('MEMBER')
            && $user->hasPermissionTo('reservations.manage');
    }
}

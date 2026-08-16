<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
// Staff-only channel for admin notifications (e.g. BE-023 "Other" queue entries).
Broadcast::channel('nep-admin', function (User $user) {
    return in_array($user->role, [
        User::ROLE_NEP_ADMIN,
        User::ROLE_NEP_COORDINATOR,
    ]);
});